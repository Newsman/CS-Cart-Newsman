<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Export\AbstractRetriever;
use Tygh\Addons\Newsman\Export\V1\ApiV1Exception;

class SubscriberUnsubscribe extends AbstractRetriever
{
    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        $email = isset($data['email']) ? trim((string) $data['email']) : '';

        if (empty($email)) {
            throw new ApiV1Exception(3200, 'Missing "email" parameter', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiV1Exception(3201, 'Invalid email address: ' . $email, 400);
        }

        $this->logger->info(sprintf('subscriber.unsubscribe: %s', $email));

        $newsmanListId = $this->config->getListId();
        $targetLists = $newsmanListId !== ''
            ? $this->config->getCscartMailingListIdsByNewsmanListId($newsmanListId)
            : array();

        if (empty($targetLists)) {
            $this->logger->debug(sprintf('subscriber.unsubscribe: skipped %s — no CS-Cart mailing list configured', $email));
            return array('success' => true, 'email' => $email, 'applied' => false);
        }

        if (empty(db_get_field("SHOW TABLES LIKE '?:subscribers'"))) {
            return array('success' => true, 'email' => $email, 'applied' => false);
        }

        $subscriberId = db_get_field("SELECT subscriber_id FROM ?:subscribers WHERE email = ?s", $email);
        if (!empty($subscriberId)) {
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
        }

        return array('success' => true, 'email' => $email, 'applied' => true, 'lists' => $targetLists);
    }
}
