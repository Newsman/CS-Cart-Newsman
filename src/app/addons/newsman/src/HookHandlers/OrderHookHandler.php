<?php

namespace Tygh\Addons\Newsman\HookHandlers;

use Tygh\Addons\Newsman\Action\Order\Save as OrderSave;
use Tygh\Addons\Newsman\Action\Order\Status as OrderStatus;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Registry;
use Tygh\Settings;

class OrderHookHandler
{
    /** @var Config */
    protected $config;

    /** @var OrderSave */
    protected $orderSave;

    /** @var OrderStatus */
    protected $orderStatus;

    /** @var Logger */
    protected $logger;

    /**
     * Registry keys read by Action\Order\Save and Action\Order\Status (via the
     * shared Api\Client and Config). Swapped per-storefront so admin-side
     * hooks (notably change_order_status_post — admin runs at company_id=0,
     * so without a swap Config::getListId() falls back to root defaults
     * instead of the order's owning storefront's Newsman list).
     *
     * Same set used by controllers/backend/subscribers.pre.php.
     *
     * @var array<string>
     */
    protected static $swappableKeys = array(
        'api_key', 'user_id', 'list_id', 'segment_id', 'authenticate_token',
        'cscart_mailing_list_id', 'double_optin', 'send_user_ip', 'server_ip',
        'api_timeout', 'log_severity', 'log_clean_days',
    );

    public function __construct(
        Config $config,
        OrderSave $orderSave,
        OrderStatus $orderStatus,
        Logger $logger
    ) {
        $this->config = $config;
        $this->orderSave = $orderSave;
        $this->orderStatus = $orderStatus;
        $this->logger = $logger;
    }

    /**
     * Hook: place_order
     *
     * @param int    $order_id
     * @param string $action
     * @param string $order_status
     * @param array  $cart
     * @param array  $auth
     */
    public function onPlaceOrder($order_id, $action, $order_status, $cart, $auth)
    {
        $this->logger->debug(sprintf(
            'OrderHookHandler: place_order received order_id=%s, action=%s, status=%s',
            (string) $order_id,
            (string) $action,
            (string) $order_status
        ));

        if (!$this->config->isEnabled()) {
            $this->logger->debug('OrderHookHandler: place_order skipped — plugin disabled');
            return;
        }
        if (empty($order_id)) {
            $this->logger->debug('OrderHookHandler: place_order skipped — empty order_id');
            return;
        }

        $this->logger->info(sprintf(
            'OrderHookHandler: place_order fired, order_id=%s, status=%s',
            $order_id,
            $order_status
        ));

        $this->withOrderStorefrontScope((int) $order_id, function () use ($order_id, $order_status, $cart, $auth) {
            $this->logger->debug(sprintf('OrderHookHandler: calling orderSave for order_id=%s', $order_id));
            $this->orderSave->execute($order_id, $order_status, $cart, $auth);
        });
    }

    /**
     * Hook: change_order_status_post
     *
     * @param int    $order_id
     * @param string $status_to
     * @param string $status_from
     * @param array  $force_notification
     * @param bool   $place_order
     * @param array  $order_info
     * @param array  $edp_data
     */
    public function onChangeOrderStatusPost(
        $order_id,
        $status_to,
        $status_from,
        $force_notification,
        $place_order,
        $order_info,
        $edp_data
    ) {
        $this->logger->debug(sprintf(
            'OrderHookHandler: change_order_status_post received order_id=%s, %s -> %s, place_order=%s',
            (string) $order_id,
            (string) $status_from,
            (string) $status_to,
            $place_order ? 'true' : 'false'
        ));

        if (!$this->config->isEnabled()) {
            $this->logger->debug('OrderHookHandler: change_order_status_post skipped — plugin disabled');
            return;
        }
        if (empty($order_id)) {
            $this->logger->debug('OrderHookHandler: change_order_status_post skipped — empty order_id');
            return;
        }

        if ($place_order) {
            $this->logger->debug(sprintf(
                'OrderHookHandler: change_order_status_post skipped for order_id=%s (triggered from place_order)',
                $order_id
            ));
            return;
        }

        $this->logger->info(sprintf(
            'OrderHookHandler: change_order_status_post fired, order_id=%s, status=%s -> %s',
            $order_id,
            $status_from,
            $status_to
        ));

        $this->withOrderStorefrontScope((int) $order_id, function () use ($order_id, $status_to) {
            $this->logger->debug(sprintf('OrderHookHandler: calling orderStatus for order_id=%s', $order_id));
            $this->orderStatus->execute($order_id, $status_to);
        }, $order_info);
    }

    /**
     * Run $fn with the runtime Newsman config swapped to the storefront that
     * owns $orderId. Resolves the order's storefront_id (and its company_id)
     * from cscart_orders, reads that storefront's Newsman settings via an
     * explicitly-scoped Settings::instance, swaps the keys read by Action\Order
     * services, runs the callback, then restores everything.
     *
     * If the order's storefront can't be resolved (legacy orders without
     * storefront_id, single-storefront installs), $fn runs with the runtime
     * Registry untouched — same behavior as before this fix.
     *
     * @param int           $orderId
     * @param callable      $fn
     * @param array|null    $orderInfo  Optional pre-loaded order row (saves a query)
     */
    protected function withOrderStorefrontScope($orderId, callable $fn, $orderInfo = null)
    {
        $row = (is_array($orderInfo) && isset($orderInfo['storefront_id']))
            ? $orderInfo
            : db_get_row('SELECT storefront_id, company_id FROM ?:orders WHERE order_id = ?i', $orderId);

        $storefrontId = isset($row['storefront_id']) ? (int) $row['storefront_id'] : 0;
        $companyId    = isset($row['company_id']) ? (int) $row['company_id'] : 0;

        if ($storefrontId === 0) {
            try {
                $fn();
            } catch (\Exception $e) {
                $this->logger->logException($e);
            }
            return;
        }

        $sfSettings = Settings::instance(array(
            'area'          => 'A',
            'company_id'    => $companyId,
            'storefront_id' => $storefrontId,
        ))->getValues('newsman', Settings::ADDON_SECTION, false, $companyId, $storefrontId);
        $sfSettings = is_array($sfSettings) ? $sfSettings : array();

        $savedRegistry = array();
        foreach (self::$swappableKeys as $k) {
            $savedRegistry[$k] = Registry::get('addons.newsman.' . $k);
        }
        $savedRuntimeCompanyId = Registry::get('runtime.company_id');
        $savedRuntimeStorefrontId = Registry::get('runtime.storefront_id');

        foreach (self::$swappableKeys as $k) {
            if (array_key_exists($k, $sfSettings)) {
                Registry::set('addons.newsman.' . $k, $sfSettings[$k]);
            }
        }
        Registry::set('runtime.company_id', $companyId);
        Registry::set('runtime.storefront_id', $storefrontId);

        try {
            $fn();
        } catch (\Exception $e) {
            $this->logger->logException($e);
        } finally {
            foreach (self::$swappableKeys as $k) {
                Registry::set('addons.newsman.' . $k, $savedRegistry[$k]);
            }
            Registry::set('runtime.company_id', $savedRuntimeCompanyId);
            Registry::set('runtime.storefront_id', $savedRuntimeStorefrontId);
        }
    }
}
