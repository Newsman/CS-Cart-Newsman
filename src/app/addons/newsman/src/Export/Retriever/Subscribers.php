<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Export\AbstractRetriever;

class Subscribers extends AbstractRetriever
{
    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        $this->logger->info('Export subscribers');

        $targetLists = $this->resolveTargetCscartLists($data);
        if (empty($targetLists)) {
            $this->logger->debug('Export subscribers: no CS-Cart mailing list configured for the requested Newsman list');
            return array();
        }

        if (empty(db_get_field("SHOW TABLES LIKE '?:subscribers'"))) {
            return array();
        }

        $data['default_page_size'] = 100000;
        $params = $this->processListParameters($data);

        $inner = db_quote(
            "SELECT ns.subscriber_id,"
            . " ns.email,"
            . " u.user_id AS customer_id,"
            . " COALESCE(u.firstname, '') AS firstname,"
            . " COALESCE(u.lastname, '') AS lastname,"
            . " COALESCE(u.phone, '') AS phone,"
            . " ns.timestamp AS date_added,"
            . " GREATEST(ns.timestamp, MAX(uml.timestamp)) AS date_modified,"
            . " MAX(uml.confirmed) AS confirmed,"
            . " 'CS-Cart mailing list' AS source_type"
            . " FROM ?:subscribers AS ns"
            . " INNER JOIN ?:user_mailing_lists AS uml ON uml.subscriber_id = ns.subscriber_id"
            . " LEFT JOIN ?:users AS u ON u.email = ns.email AND u.user_type = 'C'"
            . " WHERE uml.list_id IN (?n)"
            . " GROUP BY ns.subscriber_id",
            $targetLists
        );

        $filterSql = $this->filtersToSql($params['filters']);
        $orderSql = isset($params['sort'])
            ? ' ORDER BY ' . $params['sort'] . ' ' . $params['order']
            : ' ORDER BY s.subscriber_id ASC';

        $sql = "SELECT * FROM (" . $inner . ") AS s WHERE 1 " . $filterSql . $orderSql
            . " LIMIT " . (int) $params['start'] . ", " . (int) $params['limit'];

        $rows = db_get_array($sql);
        $sendPhone = $this->config->isRemarketingSendTelephone();

        $result = array();
        foreach ($rows as $row) {
            $item = array(
                'subscriber_id'   => (string) $row['subscriber_id'],
                'customer_id'     => isset($row['customer_id']) && $row['customer_id'] !== null ? (string) $row['customer_id'] : '',
                'firstname'       => $row['firstname'],
                'lastname'        => $row['lastname'],
                'email'           => $row['email'],
                'date_subscribed' => date('Y-m-d H:i:s', $row['date_added']),
                'date_modified'   => date('Y-m-d H:i:s', $row['date_modified']),
                'confirmed'       => (int) $row['confirmed'],
                'source'          => $row['source_type'],
            );

            if ($sendPhone && !empty($row['phone'])) {
                $item['phone'] = $this->cleanPhone($row['phone']);
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * Resolve the set of CS-Cart mailing list IDs that should be exported,
     * based on the Newsman list_id in $data (falling back to the currently
     * configured Newsman list_id) and fanning out across every storefront
     * that shares that Newsman list_id.
     *
     * @param array $data
     * @return array<int>
     */
    public function resolveTargetCscartLists(array $data)
    {
        $newsmanListId = isset($data['list_id']) && $data['list_id'] !== ''
            ? (string) $data['list_id']
            : $this->config->getListId();

        if ($newsmanListId === '') {
            return array();
        }

        return $this->config->getCscartMailingListIdsByNewsmanListId($newsmanListId);
    }

    /**
     * @param array $data
     * @return int
     */
    public function countSubscribers(array $data = array())
    {
        $targetLists = $this->resolveTargetCscartLists($data);
        if (empty($targetLists)) {
            return 0;
        }

        if (empty(db_get_field("SHOW TABLES LIKE '?:subscribers'"))) {
            return 0;
        }

        return (int) db_get_field(
            "SELECT COUNT(DISTINCT ns.subscriber_id)"
            . " FROM ?:subscribers AS ns"
            . " INNER JOIN ?:user_mailing_lists AS uml ON uml.subscriber_id = ns.subscriber_id"
            . " WHERE uml.list_id IN (?n)",
            $targetLists
        );
    }

    /**
     * @return array
     */
    public function getWhereParametersMapping()
    {
        return array(
            'created_at'     => array('field' => 's.date_added', 'quote' => true, 'type' => 'string'),
            'modified_at'    => array('field' => 's.date_modified', 'quote' => true, 'type' => 'string'),
            'subscriber_id'  => array('field' => 's.subscriber_id', 'quote' => false, 'type' => 'int'),
            'subscriber_ids' => array('field' => 's.subscriber_id', 'quote' => false, 'type' => 'int', 'multiple' => true),
            'customer_id'    => array('field' => 's.customer_id', 'quote' => false, 'type' => 'int'),
            'email'          => array('field' => 's.email', 'quote' => true, 'type' => 'string'),
            'status'         => array('field' => 's.confirmed', 'quote' => false, 'type' => 'int'),
        );
    }

    /**
     * @return array
     */
    public function getAllowedSortFields()
    {
        return array(
            'email'         => 's.email',
            'subscriber_id' => 's.subscriber_id',
            'created_at'    => 's.date_added',
            'modified_at'   => 's.date_modified',
        );
    }
}
