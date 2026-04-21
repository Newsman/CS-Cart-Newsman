<?php

namespace Tygh\Addons\Newsman\Action\Order;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Service\Remarketing\SaveOrders;

class Save
{
    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var SaveOrders */
    protected $saveOrdersService;

    /** @var StatusMapper */
    protected $statusMapper;

    public function __construct(Config $config, Logger $logger, SaveOrders $saveOrdersService)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->saveOrdersService = $saveOrdersService;
        $this->statusMapper = new StatusMapper();
    }

    /**
     * @param int    $orderId
     * @param string $orderStatus
     * @param array  $cart
     * @param array  $auth
     * @return bool
     */
    public function execute($orderId, $orderStatus, $cart, $auth)
    {
        if (!$this->config->isEnabled()) {
            $this->logger->debug(sprintf('Order #%d not saved — plugin disabled', $orderId));
            return false;
        }

        $this->logger->info(sprintf('Saving order #%d to Newsman', $orderId));

        $orderInfo = fn_get_order_info($orderId);
        if (empty($orderInfo)) {
            $this->logger->warning(sprintf('Order #%d not found', $orderId));
            return false;
        }
        $this->logger->debug(sprintf('Order #%d loaded, %d products', $orderId, isset($orderInfo['products']) ? count($orderInfo['products']) : 0));

        $products = array();
        if (!empty($orderInfo['products'])) {
            foreach ($orderInfo['products'] as $product) {
                $products[] = array(
                    'id'       => (string) $product['product_id'],
                    'name'     => isset($product['product']) ? $product['product'] : '',
                    'price'    => number_format((float) $product['price'], 2, '.', ''),
                    'quantity' => (int) $product['amount'],
                );
            }
        }

        $email = !empty($orderInfo['email']) ? $orderInfo['email'] : '';
        $firstname = !empty($orderInfo['firstname']) ? $orderInfo['firstname'] : '';
        $lastname = !empty($orderInfo['lastname']) ? $orderInfo['lastname'] : '';

        // Phone: prefer billing phone, fallback to order phone
        $phone = '';
        if (!empty($orderInfo['b_phone'])) {
            $phone = $orderInfo['b_phone'];
        } elseif (!empty($orderInfo['phone'])) {
            $phone = $orderInfo['phone'];
        }

        // Discount code (coupon)
        $discountCode = $this->getOrderCouponCodes($orderId);

        // Shipping cost
        $shipping = isset($orderInfo['shipping_cost']) ? (float) $orderInfo['shipping_cost'] : 0;

        $currency = defined('CART_PRIMARY_CURRENCY') ? CART_PRIMARY_CURRENCY : '';

        // Tax = total - subtotal
        $taxAmount = max(0, (float) $orderInfo['total'] - (float) (isset($orderInfo['subtotal']) ? $orderInfo['subtotal'] : $orderInfo['total']));

        $details = array(
            'order_no'      => (string) $orderId,
            'date'          => date('Y-m-d H:i:s', $orderInfo['timestamp']),
            'status'        => $this->statusMapper->toNewsman(!empty($orderInfo['status']) ? $orderInfo['status'] : $orderStatus),
            'total'         => number_format((float) $orderInfo['total'], 2, '.', ''),
            'discount'      => number_format(abs((float) (isset($orderInfo['discount']) ? $orderInfo['discount'] : 0)), 2, '.', ''),
            'discount_code' => $discountCode,
            'shipping'      => number_format($shipping, 2, '.', ''),
            'rebates'       => '0.00',
            'fees'          => '0.00',
            'currency'      => $currency,
            'tax'           => number_format($taxAmount, 2, '.', ''),
            'email'         => $email,
            'firstname'     => $firstname,
            'lastname'      => $lastname,
            'phone'         => $phone,
        );

        $this->logger->debug(sprintf(
            'Order #%d payload: %s',
            $orderId,
            json_encode(array('details' => $details, 'products' => $products))
        ));

        $orderRow = $details;
        $orderRow['products'] = $products;

        try {
            $result = $this->saveOrdersService->execute($this->config->getListId(), array($orderRow));
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Failed to save order #%d: %s', $orderId, $e->getMessage()));
            return false;
        }

        if (isset($result['error'])) {
            $this->logger->error(sprintf('Failed to save order #%d: %s', $orderId, $result['error']));
            return false;
        }

        $this->logger->info(sprintf('Order #%d saved to Newsman', $orderId));
        return true;
    }

    /**
     * @param int $orderId
     * @return string
     */
    public function getOrderCouponCodes($orderId)
    {
        $rows = db_get_fields(
            "SELECT data FROM ?:order_data WHERE type = 'C' AND order_id = ?i",
            $orderId
        );

        if (empty($rows)) {
            return '';
        }

        return implode(',', array_filter($rows));
    }

}
