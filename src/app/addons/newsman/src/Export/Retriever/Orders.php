<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Action\Order\StatusMapper;
use Tygh\Addons\Newsman\Export\AbstractRetriever;

class Orders extends AbstractRetriever
{
    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        $this->logger->info('Export orders');

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
        $data['default_page_size'] = 200;
        $params = $this->processListParameters($data);
        $statusMapper = new StatusMapper();

        $extraCondition = '';
        if (!empty($data['last-days'])) {
            $days = (int) $data['last-days'];
            $since = TIME - ($days * 86400);
            $extraCondition = db_quote(" AND o.timestamp >= ?i", $since);
        }

        // Multistore: only return orders belonging to companies whose
        // storefronts share the request's Newsman list_id.
        $companyIds = $this->resolveTargetCompanyIds($data);
        if (is_array($companyIds)) {
            if (empty($companyIds)) {
                return array();
            }
            $extraCondition .= db_quote(' AND o.company_id IN (?n)', $companyIds);
        }

        $filterSql = $this->filtersToSql($params['filters']);
        $orderSql = isset($params['sort'])
            ? ' ORDER BY ' . $params['sort'] . ' ' . $params['order']
            : ' ORDER BY o.order_id DESC';

        $orders = db_get_array(
            "SELECT o.order_id, o.email, o.firstname, o.lastname, o.phone, o.b_firstname, o.b_lastname,"
            . " o.b_phone, o.company, o.total, o.subtotal, o.discount, o.shipping_cost, o.status,"
            . " o.timestamp, o.user_id"
            . " FROM ?:orders AS o"
            . " WHERE o.is_parent_order != 'Y'" . $extraCondition . $filterSql
            . $orderSql
            . " LIMIT ?i, ?i",
            $params['start'],
            $params['limit']
        );

        if (empty($orders)) {
            return array();
        }

        // Batch-fetch products for all orders
        $orderIds = array();
        foreach ($orders as $order) {
            $orderIds[] = $order['order_id'];
        }
        $allProducts = $this->batchGetOrderProducts($orderIds);

        // Batch-fetch coupon codes
        $allCoupons = $this->batchGetCouponCodes($orderIds);

        $currencyCode = defined('CART_PRIMARY_CURRENCY') ? CART_PRIMARY_CURRENCY : '';

        $result = array();
        foreach ($orders as $order) {
            $orderId = $order['order_id'];
            $products = isset($allProducts[$orderId]) ? $allProducts[$orderId] : array();
            $discountCode = isset($allCoupons[$orderId]) ? $allCoupons[$orderId] : '';

            // Calculate subtotal from products
            $subtotal = 0;
            foreach ($products as $p) {
                $subtotal += (float) $p['unit_price'] * (int) $p['quantity'];
            }

            // Tax = total - subtotal (if > 0)
            $taxAmount = max(0, (float) $order['total'] - (float) $order['subtotal']);

            // Phone: prefer billing phone, fallback to order phone
            $phone = !empty($order['b_phone']) ? $order['b_phone'] : $order['phone'];

            $result[] = array(
                'id'                   => (string) $orderId,
                'billing_name'         => trim($order['b_firstname'] . ' ' . $order['b_lastname']),
                'billing_company_name' => isset($order['company']) ? $order['company'] : '',
                'billing_phone'        => !empty($phone) ? $this->cleanPhone($phone) : '',
                'customer_email'       => $order['email'],
                'customer_id'          => !empty($order['user_id']) ? (string) $order['user_id'] : '',
                'shipping_amount'      => number_format((float) $order['shipping_cost'], 2, '.', ''),
                'tax_amount'           => number_format($taxAmount, 2, '.', ''),
                'total_amount'         => number_format((float) $order['total'], 2, '.', ''),
                'currency'             => $currencyCode,
                'subtotal_amount'      => number_format($subtotal, 2, '.', ''),
                'discount'             => number_format(abs((float) $order['discount']), 2, '.', ''),
                'discount_code'        => $discountCode,
                'status'               => $statusMapper->toNewsman($order['status']),
                'date_created'         => date('Y-m-d H:i:s', $order['timestamp']),
                'date_modified'        => date('Y-m-d H:i:s', $order['timestamp']),
                'products'             => $products,
            );
        }

        return $result;
    }

    /**
     * @param array $orderIds
     * @return array
     */
    public function batchGetOrderProducts($orderIds)
    {
        if (empty($orderIds)) {
            return array();
        }

        $langCode = CART_LANGUAGE;
        $items = db_get_array(
            "SELECT od.order_id, od.product_id, od.amount, od.price,"
            . " pd.product AS name"
            . " FROM ?:order_details AS od"
            . " LEFT JOIN ?:product_descriptions AS pd ON od.product_id = pd.product_id AND pd.lang_code = ?s"
            . " WHERE od.order_id IN (?n)",
            $langCode,
            $orderIds
        );

        $grouped = array();
        foreach ($items as $item) {
            $grouped[$item['order_id']][] = array(
                'id'         => (string) $item['product_id'],
                'name'       => isset($item['name']) ? $item['name'] : '',
                'unit_price' => number_format((float) $item['price'], 2, '.', ''),
                'quantity'   => (int) $item['amount'],
            );
        }

        return $grouped;
    }

    /**
     * @param array $orderIds
     * @return array
     */
    public function batchGetCouponCodes($orderIds)
    {
        if (empty($orderIds)) {
            return array();
        }

        // CS-Cart stores coupons applied to orders in ?:order_data with type = 'C'
        $rows = db_get_array(
            "SELECT order_id, data FROM ?:order_data WHERE type = 'C' AND order_id IN (?n)",
            $orderIds
        );

        $coupons = array();
        foreach ($rows as $row) {
            $coupon = $row['data'];
            if (!empty($coupon)) {
                if (isset($coupons[$row['order_id']])) {
                    $coupons[$row['order_id']] .= ',' . $coupon;
                } else {
                    $coupons[$row['order_id']] = $coupon;
                }
            }
        }

        return $coupons;
    }

    /**
     * @return array
     */
    public function getWhereParametersMapping()
    {
        return array(
            'created_at'  => array('field' => 'o.timestamp', 'quote' => false, 'type' => 'int'),
            'modified_at' => array('field' => 'o.timestamp', 'quote' => false, 'type' => 'int'),
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
            'modified_at' => 'o.timestamp',
            'order_id'    => 'o.order_id',
        );
    }
}
