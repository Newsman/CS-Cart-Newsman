<?php

namespace Tygh\Addons\Newsman\Remarketing;

use Tygh\Addons\Newsman\Config;

class Purchase
{
    /** @var Config */
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param array $orderInfo
     * @return string
     */
    public function getHtml($orderInfo)
    {
        if (empty($orderInfo) || empty($orderInfo['order_id'])) {
            return '';
        }

        $js = '';

        // Identify customer
        $email = isset($orderInfo['email']) ? $orderInfo['email'] : '';
        if (!empty($email)) {
            $emailJs = JsHelper::escapeJs($email);
            $firstJs = JsHelper::escapeJs(isset($orderInfo['firstname']) ? $orderInfo['firstname'] : '');
            $lastJs = JsHelper::escapeJs(isset($orderInfo['lastname']) ? $orderInfo['lastname'] : '');

            $js .= "_nzm.run('identify', {"
                . "'email': '{$emailJs}'"
                . ", 'first_name': '{$firstJs}'"
                . ", 'last_name': '{$lastJs}'";

            if ($this->config->isRemarketingSendTelephone() && !empty($orderInfo['phone'])) {
                $phoneJs = JsHelper::escapeJs($orderInfo['phone']);
                $js .= ", 'phone': '{$phoneJs}'";
            }

            $js .= "});\n";
        }

        // Add products
        if (!empty($orderInfo['products'])) {
            foreach ($orderInfo['products'] as $product) {
                $productName = JsHelper::escapeJs(isset($product['product']) ? $product['product'] : '');
                $js .= "_nzm.run('ec:addProduct', {"
                    . "'id': '" . (int) $product['product_id'] . "'"
                    . ", 'name': '{$productName}'"
                    . ", 'quantity': " . (int) $product['amount']
                    . ", 'price': '" . number_format((float) $product['price'], 2, '.', '') . "'"
                    . "});\n";
            }
        }

        // Set purchase action with currency and affiliation
        $orderId = (int) $orderInfo['order_id'];
        $total = number_format((float) $orderInfo['total'], 2, '.', '');
        $discount = isset($orderInfo['discount']) ? number_format((float) $orderInfo['discount'], 2, '.', '') : '0.00';
        $shipping = isset($orderInfo['shipping_cost']) ? number_format((float) $orderInfo['shipping_cost'], 2, '.', '') : '0.00';

        $affiliation = '';
        if (isset(\Tygh::$app['storefront']) && !empty(\Tygh::$app['storefront']->name)) {
            $affiliation = JsHelper::escapeJs(\Tygh::$app['storefront']->name);
        } elseif (defined('COMPANY_NAME')) {
            $affiliation = JsHelper::escapeJs(COMPANY_NAME);
        }

        $js .= "_nzm.run('ec:setAction', 'purchase', {"
            . "'id': '{$orderId}'"
            . ", 'revenue': '{$total}'"
            . ", 'discount': '{$discount}'"
            . ", 'shipping': '{$shipping}'";

        if (!empty($affiliation)) {
            $js .= ", 'affiliation': '{$affiliation}'";
        }

        $js .= "});\n";
        $js .= "_nzm.run('send', 'pageview');\n";

        return JsHelper::wrapScript("setTimeout(function(){\n" . $js . "}, 1000);\n");
    }
}
