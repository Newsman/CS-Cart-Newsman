<?php

namespace Tygh\Addons\Newsman;

use Tygh\Addons\Newsman\Export\Renderer;

class Webhooks
{
    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var Renderer */
    protected $renderer;

    public function __construct(Config $config, Logger $logger, Renderer $renderer)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->renderer = $renderer;
    }

    /**
     * @param string $rawInput
     */
    public function execute($rawInput)
    {
        $events = json_decode($rawInput, true);

        if (!is_array($events)) {
            $events = isset($_REQUEST['newsman_events']) ? $_REQUEST['newsman_events'] : array();
            if (is_string($events)) {
                $events = json_decode($events, true);
            }
        }

        if (empty($events) || !is_array($events)) {
            $this->renderer->sendError('No events', 1010, 400);
            return;
        }

        $this->logger->info(sprintf('Webhook: received %d event(s)', count($events)));

        foreach ($events as $event) {
            $this->processEvent($event);
        }

        $this->renderer->sendSuccess(array('processed' => count($events)));
    }

    /**
     * @param array $event
     */
    public function processEvent($event)
    {
        if (!is_array($event)) {
            return;
        }

        $type = isset($event['type']) ? $event['type'] : '';
        $data = isset($event['data']) ? $event['data'] : array();
        $email = isset($data['email']) ? $data['email'] : '';

        if (empty($email)) {
            return;
        }

        $this->logger->debug(sprintf('Webhook event: %s for %s', $type, $email));

        switch ($type) {
            case 'subscribe':
            case 'subscribe_confirm':
                $this->handleSubscribe($email, $data);
                break;

            case 'unsub':
            case 'unsubscribe':
                $this->handleUnsubscribe($email);
                break;
        }
    }

    /**
     * @param string $email
     * @param array  $data
     */
    public function handleSubscribe($email, $data)
    {
        $targetLists = $this->resolveTargetCscartLists();
        if (empty($targetLists)) {
            $this->logger->debug(sprintf('Webhook: subscribe skipped for %s — no CS-Cart mailing list configured', $email));
            return;
        }

        if (empty(db_get_field("SHOW TABLES LIKE '?:subscribers'"))) {
            return;
        }

        $subscriberId = db_get_field("SELECT subscriber_id FROM ?:subscribers WHERE email = ?s", $email);
        if (empty($subscriberId)) {
            $subscriberId = db_query(
                "INSERT INTO ?:subscribers ?e",
                array('email' => $email, 'timestamp' => TIME, 'lang_code' => CART_LANGUAGE)
            );
        }

        if (empty($subscriberId)) {
            return;
        }

        foreach ($targetLists as $cscartListId) {
            db_replace_into('user_mailing_lists', array(
                'subscriber_id'   => $subscriberId,
                'list_id'         => $cscartListId,
                'activation_key'  => md5(uniqid((string) mt_rand(), true)),
                'unsubscribe_key' => md5(uniqid((string) mt_rand(), true)),
                'email'           => $email,
                'timestamp'       => TIME,
                'confirmed'       => 1,
            ));
        }

        $this->logger->info(sprintf('Webhook: subscribed %s to CS-Cart list(s) %s', $email, implode(',', $targetLists)));
    }

    /**
     * @param string $email
     */
    public function handleUnsubscribe($email)
    {
        $targetLists = $this->resolveTargetCscartLists();
        if (empty($targetLists)) {
            $this->logger->debug(sprintf('Webhook: unsubscribe skipped for %s — no CS-Cart mailing list configured', $email));
            return;
        }

        if (empty(db_get_field("SHOW TABLES LIKE '?:subscribers'"))) {
            return;
        }

        $subscriberId = db_get_field("SELECT subscriber_id FROM ?:subscribers WHERE email = ?s", $email);
        if (empty($subscriberId)) {
            return;
        }

        db_query(
            "DELETE FROM ?:user_mailing_lists WHERE subscriber_id = ?i AND list_id IN (?n)",
            $subscriberId,
            $targetLists
        );

        $remaining = (int) db_get_field(
            "SELECT COUNT(*) FROM ?:user_mailing_lists WHERE subscriber_id = ?i",
            $subscriberId
        );
        if ($remaining === 0) {
            db_query("DELETE FROM ?:subscribers WHERE subscriber_id = ?i", $subscriberId);
        }

        $this->logger->info(sprintf('Webhook: unsubscribed %s from CS-Cart list(s) %s', $email, implode(',', $targetLists)));
    }

    /**
     * Resolve the set of CS-Cart mailing list IDs that should receive the event,
     * by fanning out across every storefront that shares the current storefront's
     * Newsman list_id.
     *
     * @return array<int>
     */
    protected function resolveTargetCscartLists()
    {
        $newsmanListId = $this->config->getListId();
        if ($newsmanListId === '') {
            return array();
        }

        return $this->config->getCscartMailingListIdsByNewsmanListId($newsmanListId);
    }
}
