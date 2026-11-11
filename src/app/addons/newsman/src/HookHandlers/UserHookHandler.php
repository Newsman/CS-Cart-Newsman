<?php

namespace Tygh\Addons\Newsman\HookHandlers;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Service\UnsubscribeEmail;
use Tygh\Addons\Newsman\User\IpAddress;
use Tygh\Registry;
use Tygh\Settings;

/**
 * Unsubscribes from Newsman the email of a user who is deleted or anonymized
 * (GDPR erase request) in the store.
 *
 * User delete (Customers delete and mass delete, REST API): the email is read
 * on delete_user, while the user row still exists, and the unsubscribe is sent
 * on post_delete_user, after the row was removed. It goes only to the
 * storefronts of the user's store whose configured CS-Cart mailing list has
 * the email, the same rule as the newsletter handlers. The CS-Cart subscriber
 * row is left as it is.
 *
 * GDPR anonymize (profiles.anonymize): controllers/backend/profiles.pre.php
 * captures the email before the core controller replaces it, and the
 * unsubscribe is sent on update_user_pre, when the core controller saves the
 * anonymized user data. It goes to every storefront of the user's store, even
 * when the email is not on the CS-Cart mailing list (it can be on the Newsman
 * list from another source), and to every storefront whose mailing list has
 * the email (the anonymization removes the email from all CS-Cart mailing
 * lists). "No such subscriber" from Newsman is not an error.
 *
 * Storefronts that share a Newsman list get a single call. A Newsman or API
 * failure is logged and never stops the delete or the anonymization.
 */
class UserHookHandler
{
    /** Newsman API error code returned when the email is not on the list. */
    const API_ERROR_NO_SUCH_SUBSCRIBER = 121;

    /**
     * Registry keys read by the unsubscribe service, the API client and the
     * logger. Same set as OrderHookHandler and subscribers.pre.php.
     *
     * @var array<string>
     */
    protected static $swappableKeys = array(
        'api_key', 'user_id', 'list_id', 'segment_id', 'authenticate_token',
        'cscart_mailing_list_id', 'double_optin', 'send_user_ip', 'server_ip',
        'api_timeout', 'log_severity', 'log_clean_days',
    );

    /** @var Config */
    protected $config;

    /** @var UnsubscribeEmail */
    protected $unsubscribeService;

    /** @var IpAddress */
    protected $ipAddress;

    /** @var Logger */
    protected $logger;

    /** @var array<int,array> user_id => user, captured on delete_user */
    protected $pendingDeletes = array();

    /** @var array<int,array> user_id => user, captured before a GDPR anonymization */
    protected $pendingAnonymizations = array();

    /** @var array<int,array>|null storefront_id => Newsman configuration, per request */
    protected $storefrontConfigs = null;

    public function __construct(
        Config $config,
        UnsubscribeEmail $unsubscribeService,
        IpAddress $ipAddress,
        Logger $logger
    ) {
        $this->config = $config;
        $this->unsubscribeService = $unsubscribeService;
        $this->ipAddress = $ipAddress;
        $this->logger = $logger;
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
            if ($user !== null) {
                $this->pendingDeletes[$user['user_id']] = $user;
            }
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
            $this->logger->debug(sprintf('UserHookHandler: user_id=%d was not deleted, no Newsman unsubscribe', $user_id));
            return;
        }

        $this->unsubscribeUser($user, false);
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
            if ($user !== null) {
                $this->pendingAnonymizations[$user['user_id']] = $user;
            }
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

        $this->unsubscribeUser($user, true);
    }

    /**
     * @param array $user      user_id, email, company_id, user_type
     * @param bool  $isErasure True for a GDPR anonymization, false for a delete
     */
    protected function unsubscribeUser(array $user, $isErasure)
    {
        $reason = $isErasure ? 'GDPR anonymize' : 'user delete';

        try {
            $configs = $this->getStorefrontConfigs();
            if (empty($configs)) {
                $this->logger->debug(sprintf(
                    'UserHookHandler: %s user_id=%d, no storefront has Newsman and a CS-Cart mailing list configured',
                    $reason,
                    $user['user_id']
                ));
                return;
            }

            $targetListIds = array();
            foreach ($configs as $cfg) {
                $targetListIds[] = $cfg['target'];
            }
            $subscribedListIds = $this->getSubscribedListIds($user['email'], array_values(array_unique($targetListIds)));
            $userStorefrontIds = $this->getUserStorefrontIds($user['company_id']);

            // One call per Newsman list: storefronts sharing a list are sent once.
            $sends = array();
            foreach ($configs as $storefrontId => $cfg) {
                $isUserStorefront = in_array($storefrontId, $userStorefrontIds, true);
                $isSubscribed = in_array($cfg['target'], $subscribedListIds, true);
                $send = $isErasure ? ($isUserStorefront || $isSubscribed) : ($isUserStorefront && $isSubscribed);
                if (!$send) {
                    continue;
                }
                $listKey = $cfg['settings']['user_id'] . '|' . $cfg['settings']['list_id'];
                if (!isset($sends[$listKey])) {
                    $sends[$listKey] = $storefrontId;
                }
            }

            if (empty($sends)) {
                $this->logger->debug(sprintf(
                    'UserHookHandler: %s user_id=%d, %s is not on the configured CS-Cart mailing list, no Newsman unsubscribe',
                    $reason,
                    $user['user_id'],
                    $user['email']
                ));
                return;
            }

            foreach ($sends as $storefrontId) {
                $this->withStorefrontScope($storefrontId, $configs[$storefrontId], function () use ($user, $storefrontId, $reason) {
                    $this->sendUnsubscribe($user, $storefrontId, $reason);
                });
            }
        } catch (\Throwable $e) {
            $this->logger->logException($e);
        }
    }

    /**
     * Must run inside withStorefrontScope(), the service reads the Registry.
     *
     * @param array  $user
     * @param int    $storefrontId
     * @param string $reason
     */
    protected function sendUnsubscribe(array $user, $storefrontId, $reason)
    {
        $email = $user['email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning(sprintf(
                'UserHookHandler: %s user_id=%d, invalid email %s, no Newsman unsubscribe',
                $reason,
                $user['user_id'],
                $email
            ));
            return;
        }

        $this->logger->info(sprintf(
            'UserHookHandler: %s user_id=%d storefront=%d unsubscribe for %s',
            $reason,
            $user['user_id'],
            $storefrontId,
            $email
        ));

        $result = $this->unsubscribeService->execute($email, $this->ipAddress->getIp());

        if (!isset($result['error'])) {
            return;
        }

        if (isset($result['code']) && (int) $result['code'] === self::API_ERROR_NO_SUCH_SUBSCRIBER) {
            $this->logger->info(sprintf(
                'UserHookHandler: %s is not on Newsman list %s, nothing to unsubscribe',
                $email,
                $this->config->getListId()
            ));
            return;
        }

        $this->logger->error(sprintf(
            'UserHookHandler: %s storefront=%d unsubscribe failed for %s: %s',
            $reason,
            $storefrontId,
            $email,
            Client::errorMessage($result)
        ));
    }

    /**
     * @param int $userId
     * @return array|null
     */
    protected function getUser($userId)
    {
        if ($userId <= 0) {
            return null;
        }

        $row = db_get_row(
            'SELECT user_id, email, company_id, user_type FROM ?:users WHERE user_id = ?i',
            $userId
        );
        if (empty($row) || trim((string) $row['email']) === '') {
            return null;
        }

        return array(
            'user_id'    => (int) $row['user_id'],
            'email'      => trim((string) $row['email']),
            'company_id' => (int) $row['company_id'],
            'user_type'  => (string) $row['user_type'],
        );
    }

    /**
     * Storefronts with Newsman configured: API access, a Newsman list and a
     * CS-Cart mailing list. Read with each storefront's own company_id, the
     * admin runtime company would hide per-storefront values.
     *
     * @return array<int,array> storefront_id => company_id, target, settings
     */
    protected function getStorefrontConfigs()
    {
        if ($this->storefrontConfigs !== null) {
            return $this->storefrontConfigs;
        }

        $configs = array();
        foreach ($this->config->getAllStorefrontIds() as $storefrontId) {
            $companyId = (int) db_get_field(
                'SELECT company_id FROM ?:storefronts_companies WHERE storefront_id = ?i LIMIT 1',
                $storefrontId
            );
            $settings = Settings::instance(array(
                'area'          => 'A',
                'company_id'    => $companyId,
                'storefront_id' => $storefrontId,
            ))->getValues(Config::SECTION_NAME, Settings::ADDON_SECTION, false, $companyId, $storefrontId);
            $settings = is_array($settings) ? $settings : array();

            $targetListId = isset($settings['cscart_mailing_list_id']) ? (int) $settings['cscart_mailing_list_id'] : 0;
            if (empty($settings['api_key']) || empty($settings['user_id']) || empty($settings['list_id']) || $targetListId === 0) {
                continue;
            }

            $configs[(int) $storefrontId] = array(
                'company_id' => $companyId,
                'target'     => $targetListId,
                'settings'   => $settings,
            );
        }

        $this->storefrontConfigs = $configs;

        return $configs;
    }

    /**
     * Storefronts where the user is a customer: the storefronts of the user's
     * store when users are not shared between stores, otherwise all of them.
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
     * CS-Cart mailing lists, among $listIds, that have the email.
     *
     * @param string     $email
     * @param array<int> $listIds
     * @return array<int>
     */
    protected function getSubscribedListIds($email, array $listIds)
    {
        if (empty($listIds) || Registry::get('addons.newsletters.status') !== 'A') {
            return array();
        }

        return array_map('intval', db_get_fields(
            'SELECT DISTINCT m.list_id FROM ?:subscribers AS s'
            . ' INNER JOIN ?:user_mailing_lists AS m ON m.subscriber_id = s.subscriber_id'
            . ' WHERE s.email = ?s AND m.list_id IN (?n)',
            $email,
            $listIds
        ));
    }

    /**
     * Run $fn with the runtime Newsman settings of $storefrontId, then
     * restore them.
     *
     * @param int      $storefrontId
     * @param array    $cfg Entry of getStorefrontConfigs()
     * @param callable $fn
     */
    protected function withStorefrontScope($storefrontId, array $cfg, callable $fn)
    {
        $savedRegistry = array();
        foreach (self::$swappableKeys as $k) {
            $savedRegistry[$k] = Registry::get('addons.newsman.' . $k);
        }
        $savedRuntimeCompanyId = Registry::get('runtime.company_id');
        $savedRuntimeStorefrontId = Registry::get('runtime.storefront_id');

        foreach (self::$swappableKeys as $k) {
            if (array_key_exists($k, $cfg['settings'])) {
                Registry::set('addons.newsman.' . $k, $cfg['settings'][$k]);
            }
        }
        Registry::set('runtime.company_id', $cfg['company_id']);
        Registry::set('runtime.storefront_id', $storefrontId);

        try {
            $fn();
        } catch (\Throwable $e) {
            $this->logger->logException($e);
        } finally {
            foreach (self::$swappableKeys as $k) {
                Registry::set('addons.newsman.' . $k, $savedRegistry[$k]);
            }
            Registry::set('runtime.company_id', $savedRuntimeCompanyId);
            Registry::set('runtime.storefront_id', $savedRuntimeStorefrontId);
        }
    }
}
