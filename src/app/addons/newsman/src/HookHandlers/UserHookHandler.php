<?php

namespace Tygh\Addons\Newsman\HookHandlers;

use Tygh\Addons\Newsman\Action\Anonymise\Email as AnonymiseAction;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Registry;

/**
 * Anonymises in Newsman the email of a user who is deleted or anonymized
 * (GDPR erase request) in the store, whatever the user's newsletter status.
 *
 * User delete (Customers delete and mass delete, REST API, any fn_delete_user
 * call): the email is read on delete_user, while the user row still exists,
 * and the anonymisation is sent on post_delete_user, after the row was
 * removed. It goes to the Newsman lists of the user's storefronts. A Newsman
 * list where the storefront setting "Anonymise in Newsman when customers are
 * deleted" is off gets an unsubscribe instead. The CS-Cart subscriber row is
 * left as it is.
 *
 * GDPR anonymize (profiles.anonymize) always anonymises, whatever that
 * setting: controllers/backend/profiles.pre.php
 * captures the email before the core controller replaces it, and the
 * anonymisation is sent on update_user_pre, when the core controller saves
 * the anonymized user data. It goes to the Newsman lists of the user's
 * storefronts. When the anonymization also deletes the CS-Cart newsletter
 * subscriber (the newsletters add-on does that in "All stores" mode), it goes
 * to every storefront, the same as a subscriber delete.
 *
 * Storefront delete: the core deletes the storefront's users one by one.
 * Those deletes send nothing to Newsman: the merchant removes the contacts in
 * Newsman. Only the users of the storefront being deleted are skipped.
 */
class UserHookHandler
{
    /** @var Config */
    protected $config;

    /** @var AnonymiseAction */
    protected $anonymiseAction;

    /** @var Logger */
    protected $logger;

    /** @var array<int,array> user_id => user, captured on delete_user */
    protected $pendingDeletes = array();

    /** @var array<int,array> user_id => user, captured before a GDPR anonymization */
    protected $pendingAnonymizations = array();

    /** @var array<int,bool> company_id => true, storefronts being deleted in this request */
    protected $deletedCompanyIds = array();

    public function __construct(Config $config, AnonymiseAction $anonymiseAction, Logger $logger)
    {
        $this->config = $config;
        $this->anonymiseAction = $anonymiseAction;
        $this->logger = $logger;
    }

    /**
     * Hook: delete_company_pre
     *
     * A storefront (company) is about to be deleted. With users not shared
     * between stores the core then deletes all its users, otherwise its
     * administrators.
     *
     * @param int  $company_id
     * @param bool $can_delete
     */
    public function onDeleteCompanyPre($company_id, $can_delete = true)
    {
        $company_id = (int) $company_id;
        if ($company_id <= 0) {
            return;
        }
        $this->deletedCompanyIds[$company_id] = true;

        if ($this->config->isActive()) {
            $this->logger->info(sprintf(
                'UserHookHandler: storefront company_id=%d is being deleted, its deleted users get no Newsman action',
                $company_id
            ));
        }
    }

    /**
     * Hook: delete_company
     *
     * @param int $company_id
     */
    public function onDeleteCompany($company_id)
    {
        unset($this->deletedCompanyIds[(int) $company_id]);
    }

    /**
     * Hook: delete_user
     *
     * Runs after the delete permission checks and before the user row is
     * removed, so the email can still be read.
     *
     * @param int   $user_id
     * @param array $user_data user_id, is_root, company_id
     */
    public function onDeleteUser($user_id, $user_data = array())
    {
        try {
            if (!$this->config->isActive()) {
                return;
            }
            $user = $this->getUser((int) $user_id);
            if ($user === null) {
                return;
            }
            if (isset($this->deletedCompanyIds[$user['company_id']])) {
                $this->logger->debug(sprintf(
                    'UserHookHandler: user_id=%d is deleted with its storefront company_id=%d, no Newsman action',
                    $user['user_id'],
                    $user['company_id']
                ));
                return;
            }
            $this->pendingDeletes[$user['user_id']] = $user;
        } catch (\Throwable $e) {
            $this->logger->logException($e);
        }
    }

    /**
     * Hook: post_delete_user
     *
     * @param int   $user_id
     * @param array $user_data user_id, is_root, company_id
     * @param mixed $result    Number of deleted user rows
     */
    public function onPostDeleteUser($user_id, $user_data = array(), $result = null)
    {
        $user_id = (int) $user_id;
        if (!isset($this->pendingDeletes[$user_id])) {
            return;
        }
        $user = $this->pendingDeletes[$user_id];
        unset($this->pendingDeletes[$user_id]);

        if (empty($result)) {
            $this->logger->debug(sprintf('UserHookHandler: user_id=%d was not deleted, nothing sent to Newsman', $user_id));
            return;
        }

        try {
            $this->anonymiseAction->executeForDelete(
                $user['email'],
                $this->getUserStorefrontIds($user['company_id']),
                sprintf('user delete user_id=%d', $user_id)
            );
        } catch (\Throwable $e) {
            $this->logger->logException($e);
        }
    }

    /**
     * Called by controllers/backend/profiles.pre.php for a profiles.anonymize
     * request, before the core controller replaces the email.
     *
     * @param int $userId
     */
    public function captureAnonymization($userId)
    {
        try {
            if (!$this->config->isActive()) {
                return;
            }
            $user = $this->getUser((int) $userId);
            if ($user === null) {
                return;
            }
            $user['deletes_subscriber'] = $this->isSubscriberDeletedByAnonymization($user['email']);
            $this->pendingAnonymizations[$user['user_id']] = $user;
        } catch (\Throwable $e) {
            $this->logger->logException($e);
        }
    }

    /**
     * Hook: update_user_pre
     *
     * The anonymization saves the anonymized user data with fn_update_user().
     * Fires for every user update; only a user captured by
     * captureAnonymization() in this request is handled, once.
     *
     * @param int $user_id
     */
    public function onUpdateUserPre($user_id)
    {
        $user_id = (int) $user_id;
        if (!isset($this->pendingAnonymizations[$user_id])) {
            return;
        }
        $user = $this->pendingAnonymizations[$user_id];
        unset($this->pendingAnonymizations[$user_id]);

        try {
            $storefrontIds = $user['deletes_subscriber']
                ? $this->anonymiseAction->getConnectedStorefrontIds()
                : $this->getUserStorefrontIds($user['company_id']);

            $this->anonymiseAction->execute(
                $user['email'],
                $storefrontIds,
                sprintf('GDPR anonymize user_id=%d', $user_id)
            );
        } catch (\Throwable $e) {
            $this->logger->logException($e);
        }
    }

    /**
     * @param int $userId
     * @return array|null user_id, email, company_id
     */
    protected function getUser($userId)
    {
        if ($userId <= 0) {
            return null;
        }

        $row = db_get_row(
            'SELECT user_id, email, company_id FROM ?:users WHERE user_id = ?i',
            $userId
        );
        if (empty($row) || trim((string) $row['email']) === '') {
            return null;
        }

        return array(
            'user_id'    => (int) $row['user_id'],
            'email'      => trim((string) $row['email']),
            'company_id' => (int) $row['company_id'],
        );
    }

    /**
     * Storefronts where the user is a customer: the storefronts of the user's
     * store when users are not shared between stores. When they are shared
     * (Ultimate "Share users among stores"), or the user belongs to no store,
     * the user can sign in and buy on every storefront, so all of them.
     *
     * @param int $companyId
     * @return array<int>
     */
    protected function getUserStorefrontIds($companyId)
    {
        $all = array_map('intval', $this->config->getAllStorefrontIds());

        if ($companyId <= 0
            || !fn_allowed_for('ULTIMATE')
            || Registry::get('settings.Stores.share_users') === 'Y'
        ) {
            return $all;
        }

        $ids = array_map('intval', db_get_fields(
            'SELECT storefront_id FROM ?:storefronts_companies WHERE company_id = ?i',
            $companyId
        ));

        return !empty($ids) ? $ids : $all;
    }

    /**
     * Whether the GDPR anonymization also deletes the CS-Cart newsletter
     * subscriber with this email: the newsletters add-on deletes it only when
     * the admin works in "All stores" mode.
     *
     * @param string $email
     * @return bool
     */
    protected function isSubscriberDeletedByAnonymization($email)
    {
        if (Registry::get('addons.newsletters.status') !== 'A' || Registry::get('runtime.company_id')) {
            return false;
        }

        return (bool) db_get_field('SELECT subscriber_id FROM ?:subscribers WHERE email = ?s', $email);
    }
}
