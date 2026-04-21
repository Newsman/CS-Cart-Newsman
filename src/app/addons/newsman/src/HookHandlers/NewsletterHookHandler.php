<?php

namespace Tygh\Addons\Newsman\HookHandlers;

use Tygh\Addons\Newsman\Action\Subscribe\Email as SubscribeAction;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Registry;

class NewsletterHookHandler
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
     * Hook: newsletters_update_subscriptions_post
     *
     * @param int   $subscriber_id
     * @param array $user_list_ids
     * @param array $subscriber
     * @param array $params
     */
    public function onNewsletterUpdateSubscriptions($subscriber_id, $user_list_ids, $subscriber, $params)
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        // Admin subscribers.manage flow is handled per-storefront by
        // controllers/backend/subscribers.pre.php — skip here to avoid
        // double-firing for the root storefront.
        if (Registry::ifGet('runtime.newsman.admin_handled', false)) {
            return;
        }

        $email = isset($subscriber['email']) ? $subscriber['email'] : '';
        if (empty($email)) {
            $this->logger->debug('NewsletterHookHandler: skipping, no email in subscriber data');
            return;
        }

        $targetListId = $this->config->getCscartMailingListId();
        if ($targetListId === '') {
            $this->logger->debug('NewsletterHookHandler: skipping, no CS-Cart mailing list configured in Newsman settings');
            return;
        }

        $currentLists = is_array($user_list_ids) ? array_map('intval', $user_list_ids) : array();
        $subscribed = in_array((int) $targetListId, $currentLists, true);

        // Pre-controllers (subscribers.pre.php, profiles.pre.php) record whether
        // this email was already on the configured list before the request.
        // Absent marker means "unknown / treat as not previously subscribed".
        $preState = (array) Registry::ifGet('runtime.newsman.pre_subscribed', array());
        $preKey = strtolower($email);
        $hasPreState = array_key_exists($preKey, $preState);
        $wasSubscribed = $hasPreState ? (bool) $preState[$preKey] : false;

        try {
            if ($subscribed) {
                if ($hasPreState && $wasSubscribed) {
                    $this->logger->debug('NewsletterHookHandler: skipping subscribe, email=' . $email . ' already subscribed to configured list');
                    return;
                }
                $this->logger->info('NewsletterHookHandler: newsletter subscribe for email=' . $email . ', subscriber_id=' . $subscriber_id);
                $firstname = isset($subscriber['firstname']) ? $subscriber['firstname'] : '';
                $lastname = isset($subscriber['lastname']) ? $subscriber['lastname'] : '';
                $this->subscribeAction->subscribe($email, $firstname, $lastname);
            } else {
                if (!$wasSubscribed) {
                    $this->logger->debug('NewsletterHookHandler: skipping unsubscribe, email=' . $email . ' was not previously subscribed to configured list');
                    return;
                }
                $this->logger->info('NewsletterHookHandler: newsletter unsubscribe for email=' . $email . ', subscriber_id=' . $subscriber_id);
                $this->subscribeAction->unsubscribe($email);
            }
        } catch (\Exception $e) {
            $this->logger->logException($e);
        }
    }
}
