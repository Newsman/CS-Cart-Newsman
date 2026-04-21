<?php

namespace Tygh\Addons\Newsman\HookHandlers;

use Tygh\Addons\Newsman\Action\Subscribe\Email as SubscribeAction;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

class ProfileHookHandler
{
    /** @var Config */
    protected $config;

    /** @var SubscribeAction */
    protected $subscribeAction;

    /** @var Logger */
    protected $logger;

    public function __construct(Config $config, SubscribeAction $subscribeAction, Logger $logger)
    {
        $this->config = $config;
        $this->subscribeAction = $subscribeAction;
        $this->logger = $logger;
    }

    /**
     * Hook: update_profile
     *
     * Bridges CS-Cart's registration gap: the register form posts a
     * `mailing_lists[]` checkbox set, but the built-in newsletters addon
     * only calls `fn_update_subscriber` on profile *update*, ignoring it
     * on *add*. Here we detect opt-in at registration and forward it to
     * Newsman when the admin-configured mailing list is selected.
     *
     * Subsequent updates (profile page, checkout) fire
     * `newsletters_update_subscriptions_post`, which the NewsletterHookHandler
     * already handles, so we don't act on `update` here.
     *
     * @param string $action            'add' or 'update'
     * @param array  $user_data         Updated user data
     * @param array  $current_user_data Previous user data
     */
    public function onUpdateProfile($action, $user_data, $current_user_data)
    {
        if ($action !== 'add') {
            return;
        }

        if (!$this->config->isEnabled()) {
            return;
        }

        $userType = isset($user_data['user_type']) ? $user_data['user_type'] : '';
        if ($userType !== 'C' && $userType !== '') {
            $this->logger->debug('ProfileHookHandler: skipping non-customer user_type=' . $userType);
            return;
        }

        $email = isset($user_data['email']) ? $user_data['email'] : '';
        if (empty($email)) {
            $this->logger->debug('ProfileHookHandler: skipping, no email in user_data');
            return;
        }

        $targetListId = $this->config->getCscartMailingListId();
        if ($targetListId === '') {
            $this->logger->debug('ProfileHookHandler: skipping, no CS-Cart mailing list configured in Newsman settings');
            return;
        }

        $submitted = isset($_REQUEST['mailing_lists']) && is_array($_REQUEST['mailing_lists'])
            ? array_map('intval', $_REQUEST['mailing_lists'])
            : array();

        if (!in_array((int) $targetListId, $submitted, true)) {
            $this->logger->debug('ProfileHookHandler: registration skipped, configured list not checked, email=' . $email);
            return;
        }

        $userId = isset($user_data['user_id']) ? $user_data['user_id'] : 0;

        try {
            $firstname = isset($user_data['firstname']) ? $user_data['firstname'] : '';
            $lastname = isset($user_data['lastname']) ? $user_data['lastname'] : '';

            $this->logger->info('ProfileHookHandler: registration opt-in, subscribing user_id=' . $userId . ', email=' . $email);
            $this->subscribeAction->subscribe($email, $firstname, $lastname);
        } catch (\Exception $e) {
            $this->logger->logException($e);
        }
    }
}
