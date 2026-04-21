<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Export\AbstractRetriever;
use Tygh\Addons\Newsman\Export\V1\ApiV1Exception;

class SubscriberSubscribe extends AbstractRetriever
{
    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        $email = isset($data['email']) ? trim((string) $data['email']) : '';

        if (empty($email)) {
            throw new ApiV1Exception(3100, 'Missing "email" parameter', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiV1Exception(3101, 'Invalid email address: ' . $email, 400);
        }

        $this->logger->info(sprintf('subscriber.subscribe: %s', $email));

        $newsmanListId = $this->config->getListId();
        $targetLists = $newsmanListId !== ''
            ? $this->config->getCscartMailingListIdsByNewsmanListId($newsmanListId)
            : array();

        if (empty($targetLists)) {
            $this->logger->debug(sprintf('subscriber.subscribe: skipped %s — no CS-Cart mailing list configured', $email));
            return array('success' => true, 'email' => $email, 'applied' => false);
        }

        if (empty(db_get_field("SHOW TABLES LIKE '?:subscribers'"))) {
            return array('success' => true, 'email' => $email, 'applied' => false);
        }

        $subscriberId = db_get_field("SELECT subscriber_id FROM ?:subscribers WHERE email = ?s", $email);
        if (empty($subscriberId)) {
            $subscriberId = db_query(
                "INSERT INTO ?:subscribers ?e",
                array('email' => $email, 'timestamp' => TIME, 'lang_code' => CART_LANGUAGE)
            );
        }

        if (!empty($subscriberId)) {
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
        }

        return array('success' => true, 'email' => $email, 'applied' => true, 'lists' => $targetLists);
    }
}
