<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Export\AbstractRetriever;

class Customers extends AbstractRetriever
{
    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        $this->logger->info('Export customers');

        try {
            return $this->doProcess($data);
        } catch (\Exception $e) {
            $this->logger->logException($e);
            throw $e;
        }
    }

    /**
     * @param array $data
     * @return array
     */
    public function doProcess($data = array())
    {
        $data['default_page_size'] = 1000;
        $params = $this->processListParameters($data);
        $filterSql = $this->filtersToSql($params['filters']);

        // Multistore: only return customers belonging to companies whose
        // storefronts share the request's Newsman list_id.
        $companyIds = $this->resolveTargetCompanyIds($data);
        $companyCondition = '';
        if (is_array($companyIds)) {
            if (empty($companyIds)) {
                return array();
            }
            $companyCondition = db_quote(' AND c.company_id IN (?n)', $companyIds);
        }

        $orderSql = isset($params['sort'])
            ? ' ORDER BY ' . $params['sort'] . ' ' . $params['order']
            : ' ORDER BY c.user_id ASC';

        $users = db_get_array(
            "SELECT c.user_id, c.email, c.firstname, c.lastname, c.phone,"
            . " c.status, c.timestamp, c.user_type,"
            . " CONCAT(c.firstname, ' ', c.lastname) AS name,"
            . " GROUP_CONCAT(DISTINCT ugl.usergroup_id) AS usergroup_ids"
            . " FROM ?:users AS c"
            . " LEFT JOIN ?:usergroup_links AS ugl ON ugl.user_id = c.user_id AND ugl.status = 'A'"
            . " WHERE c.user_type = 'C'" . $companyCondition . $filterSql
            . " GROUP BY c.user_id"
            . $orderSql
            . " LIMIT ?i, ?i",
            $params['start'],
            $params['limit']
        );

        $sendPhone = $this->config->isRemarketingSendTelephone();

        $result = array();
        foreach ($users as $user) {
            $item = array(
                'customer_id'  => (string) $user['user_id'],
                'firstname'    => $user['firstname'],
                'lastname'     => $user['lastname'],
                'email'        => $user['email'],
                'date_created' => date('Y-m-d H:i:s', $user['timestamp']),
                'source'       => 'CS-Cart customers',
            );

            // Customer groups
            $groups = array();
            if (!empty($user['usergroup_ids'])) {
                $groupIds = explode(',', $user['usergroup_ids']);
                foreach ($groupIds as $gid) {
                    $gid = trim($gid);
                    if (!empty($gid)) {
                        $groupName = db_get_field(
                            "SELECT usergroup FROM ?:usergroup_descriptions"
                            . " WHERE usergroup_id = ?i AND lang_code = ?s LIMIT 1",
                            (int) $gid,
                            CART_LANGUAGE
                        );
                        $groups[] = array('id' => $gid, 'name' => $groupName ? $groupName : '');
                    }
                }
            }
            $item['customer_groups'] = $groups;

            if ($sendPhone && !empty($user['phone'])) {
                $item['phone'] = $this->cleanPhone($user['phone']);
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getWhereParametersMapping()
    {
        return array(
            'created_at'        => array('field' => 'c.timestamp', 'quote' => false, 'type' => 'int'),
            'modified_at'       => array('field' => 'c.timestamp', 'quote' => false, 'type' => 'int'),
            'customer_id'       => array('field' => 'c.user_id', 'quote' => false, 'type' => 'int'),
            'customer_ids'      => array('field' => 'c.user_id', 'quote' => false, 'type' => 'int', 'multiple' => true),
            'email'             => array('field' => 'c.email', 'quote' => true, 'type' => 'string'),
            'firstname'         => array('field' => 'c.firstname', 'quote' => true, 'type' => 'string'),
            'lastname'          => array('field' => 'c.lastname', 'quote' => true, 'type' => 'string'),
            'customer_group_id' => array('field' => 'ugl.usergroup_id', 'quote' => false, 'type' => 'int'),
            'status'            => array('field' => 'c.status', 'quote' => true, 'type' => 'string'),
        );
    }

    /**
     * @return array
     */
    public function getAllowedSortFields()
    {
        return array(
            'email'       => 'c.email',
            'firstname'   => 'c.firstname',
            'lastname'    => 'c.lastname',
            'customer_id' => 'c.user_id',
            'created_at'  => 'c.timestamp',
            'status'      => 'c.status',
        );
    }
}
