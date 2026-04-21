<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Action\Order\StatusMapper;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Export\AbstractRetriever;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Service\Remarketing\SaveOrders;

class SendOrders extends AbstractRetriever
{
    const DEFAULT_PAGE_SIZE = 200;
    const BATCH_SIZE = 500;

    /** @var SaveOrders */
    protected $saveOrdersService;

    /** @var StatusMapper */
    protected $statusMapper;

    public function __construct(Config $config, Logger $logger, SaveOrders $saveOrdersService)
    {
        parent::__construct($config, $logger);
        $this->saveOrdersService = $saveOrdersService;
        $this->statusMapper = new StatusMapper();
    }

    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        $this->logger->info('Send orders');

        $data['default_page_size'] = self::DEFAULT_PAGE_SIZE;
        $params = $this->processListParameters($data);
        $filterSql = $this->filtersToSql($params['filters']);

        $extraCondition = '';
        if (!empty($data['last-days'])) {
            $days = (int) $data['last-days'];
            $since = TIME - ($days * 86400);
            $extraCondition = db_quote(" AND o.timestamp >= ?i", $since);
        }

        $orderSql = isset($params['sort'])
            ? ' ORDER BY ' . $params['sort'] . ' ' . $params['order']
            : ' ORDER BY o.order_id DESC';

        $orders = db_get_array(
            "SELECT o.order_id, o.email, o.firstname, o.lastname, o.phone, o.b_phone,"
            . " o.total, o.discount, o.shipping_cost, o.status, o.timestamp"
            . " FROM ?:orders AS o"
            . " WHERE o.is_parent_order != 'Y'" . $extraCondition . $filterSql
            . $orderSql
            . " LIMIT ?i, ?i",
            $params['start'],
            $params['limit']
        );

        if (empty($orders)) {
            return array('status' => 'No orders found.');
        }

        // Batch-fetch products
        $orderIds = array();
        foreach ($orders as $order) {
            $orderIds[] = $order['order_id'];
        }

        $langCode = CART_LANGUAGE;
        $allProducts = db_get_array(
            "SELECT od.order_id, od.product_id, od.amount, od.price"
            . " FROM ?:order_details AS od"
            . " WHERE od.order_id IN (?n)",
            $orderIds
        );

        $productsByOrder = array();
        foreach ($allProducts as $item) {
            $productsByOrder[$item['order_id']][] = array(
                'id'       => (string) $item['product_id'],
                'quantity' => (int) $item['amount'],
                'price'    => number_format((float) $item['price'], 2, '.', ''),
            );
        }

        // Batch-fetch coupon codes
        $couponRows = db_get_array(
            "SELECT order_id, data FROM ?:order_data WHERE type = 'C' AND order_id IN (?n)",
            $orderIds
        );
        $couponsByOrder = array();
        foreach ($couponRows as $row) {
            if (!empty($row['data'])) {
                if (isset($couponsByOrder[$row['order_id']])) {
                    $couponsByOrder[$row['order_id']] .= ',' . $row['data'];
                } else {
                    $couponsByOrder[$row['order_id']] = $row['data'];
                }
            }
        }

        // Build order payloads
        $orderPayloads = array();
        $total = count($orders);

        foreach ($orders as $order) {
            $oid = $order['order_id'];
            $phone = !empty($order['b_phone']) ? $order['b_phone'] : $order['phone'];

            $orderPayloads[] = array(
                'order_no'      => (string) $oid,
                'lastname'      => $order['lastname'],
                'firstname'     => $order['firstname'],
                'email'         => $order['email'],
                'phone'         => !empty($phone) ? $this->cleanPhone($phone) : '',
                'status'        => $this->statusMapper->toNewsman($order['status']),
                'created_at'    => date('Y-m-d H:i:s', $order['timestamp']),
                'discount_code' => isset($couponsByOrder[$oid]) ? $couponsByOrder[$oid] : '',
                'discount'      => number_format(abs((float) $order['discount']), 2, '.', ''),
                'shipping'      => number_format((float) $order['shipping_cost'], 2, '.', ''),
                'rebates'       => 0,
                'fees'          => 0,
                'total'         => number_format((float) $order['total'], 2, '.', ''),
                'products'      => isset($productsByOrder[$oid]) ? $productsByOrder[$oid] : array(),
            );
        }

        // Send in batches
        $listId = $this->config->getListId();
        $batches = array_chunk($orderPayloads, self::BATCH_SIZE);
        $results = array();
        $sent = 0;

        foreach ($batches as $batch) {
            try {
                $result = $this->saveOrdersService->execute($listId, $batch);
                $results[] = $result;
                $sent += count($batch);
            } catch (\Exception $e) {
                $this->logger->error('SendOrders batch error: ' . $e->getMessage());
                $results[] = array('error' => $e->getMessage());
            }
        }

        return array(
            'status'  => sprintf('Sent to NewsMAN %d orders out of total %d.', $sent, $total),
            'results' => $results,
        );
    }

    /**
     * @return array
     */
    public function getWhereParametersMapping()
    {
        return array(
            'created_at'  => array('field' => 'o.timestamp', 'quote' => false, 'type' => 'int'),
            'modified_at' => array('field' => 'o.updated_timestamp', 'quote' => false, 'type' => 'int'),
            'order_id'    => array('field' => 'o.order_id', 'quote' => false, 'type' => 'int'),
            'order_ids'   => array('field' => 'o.order_id', 'quote' => false, 'type' => 'int', 'multiple' => true),
        );
    }

    /**
     * @return array
     */
    public function getAllowedSortFields()
    {
        return array(
            'created_at'  => 'o.timestamp',
            'order_id'    => 'o.order_id',
        );
    }
}
