<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Service\Remarketing\SaveOrders;

class CronOrders extends SendOrders
{
    const DEFAULT_PAGE_SIZE = 200;

    public function __construct(Config $config, Logger $logger, SaveOrders $saveOrdersService)
    {
        parent::__construct($config, $logger, $saveOrdersService);
    }

    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        // If limit already set, do a single page
        if (isset($data['limit'])) {
            return parent::process($data);
        }

        // Handle last-days as created_at filter for auto-pagination
        if (!empty($data['last-days'])) {
            $days = (int) $data['last-days'];
            $since = TIME - ($days * 86400);
            if (!isset($data['created_at'])) {
                $data['created_at'] = array('from' => $since);
            }
        }

        // Auto-paginate through all orders
        $count = $this->getOrderCount($data);
        if ($count === 0) {
            return array('status' => 'No orders found.');
        }

        $results = array();
        for ($start = 0; $start < $count; $start += self::DEFAULT_PAGE_SIZE) {
            $pageData = $data;
            $pageData['start'] = $start;
            $pageData['limit'] = self::DEFAULT_PAGE_SIZE;

            $result = parent::process($pageData);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * @param array $data
     * @return int
     */
    public function getOrderCount($data = array())
    {
        $extraCondition = '';
        if (!empty($data['last-days'])) {
            $days = (int) $data['last-days'];
            $since = TIME - ($days * 86400);
            $extraCondition = db_quote(" AND o.timestamp >= ?i", $since);
        }

        return (int) db_get_field(
            "SELECT COUNT(*) FROM ?:orders AS o WHERE o.is_parent_order != 'Y'" . $extraCondition
        );
    }
}
