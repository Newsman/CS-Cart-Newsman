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

        $orderId = (int) $orderInfo['order_id'];
        $js = $this->getDeliveryGuardJs($orderId);

        // Customer identity
        $email = isset($orderInfo['email']) ? $orderInfo['email'] : '';
        if (!empty($email)) {
            $emailJs = JsHelper::escapeJs($email);
            $firstJs = JsHelper::escapeJs(isset($orderInfo['firstname']) ? $orderInfo['firstname'] : '');
            $lastJs = JsHelper::escapeJs(isset($orderInfo['lastname']) ? $orderInfo['lastname'] : '');

            $js .= "var nzmIdentity = {"
                . "'email': '{$emailJs}'"
                . ", 'first_name': '{$firstJs}'"
                . ", 'last_name': '{$lastJs}'";

            if ($this->config->isRemarketingSendTelephone() && !empty($orderInfo['phone'])) {
                $phoneJs = JsHelper::escapeJs($orderInfo['phone']);
                $js .= ", 'phone': '{$phoneJs}'";
            }

            $js .= "};\n";
        } else {
            $js .= "var nzmIdentity = null;\n";
        }

        $js .= "var nzmPurchaseQueued = false;\n"
            . "function nzmSendPurchase() {\n"
            . "if (nzmPurchaseQueued) {\n"
            . "return;\n"
            . "}\n"
            . "nzmPurchaseQueued = true;\n"
            . "try { nzmWatchDelivery(0); } catch (e) {}\n";

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
        $js .= "}\n";

        // Reload of a thank-you page whose purchase Newsman already received
        $js .= "if (nzmAlreadySent) {\n"
            . "if (nzmIdentity) {\n"
            . "_nzm.identify(nzmIdentity);\n"
            . "}\n"
            . "_nzm.run('send', 'pageview');\n"
            . "return;\n"
            . "}\n";

        // The tracker drops a purchase from a visitor without a tracking id, so a
        // visitor not identified yet sends it once identify has stored the id.
        $js .= "if (!nzmIdentity) {\n"
            . "nzmSendPurchase();\n"
            . "} else if (/(?:^|;\\s*)_nzm_id_[^=]*=[^;]/.test(document.cookie)) {\n"
            . "_nzm.identify(nzmIdentity);\n"
            . "nzmSendPurchase();\n"
            . "} else {\n"
            . "_nzm.identify(nzmIdentity, undefined, nzmSendPurchase);\n"
            . "setTimeout(nzmSendPurchase, 5000);\n"
            . "}\n";

        return JsHelper::wrapScript("setTimeout(function(){\n" . $js . "}, 1000);\n");
    }

    /**
     * Remembers in localStorage the orders whose purchase Newsman received, so a
     * reload of the thank-you page does not send the purchase again.
     *
     * An order counts as received only after the tracker confirmed a purchase
     * (_nzm.onPurchase, called on an OK response) while the request for this
     * order, issued on this page, is no longer waiting in the tracker's
     * nzm_retry queue. Anything less (tracker blocked, request failed, consent
     * denied, storage unavailable) leaves the order unmarked and the purchase is
     * sent again on the next view.
     *
     * @param int $orderId
     * @return string
     */
    protected function getDeliveryGuardJs($orderId)
    {
        $js = <<<'JS'
var nzmOrderId = '__ORDER_ID__';
var nzmSentKey = 'newsman_purchases_sent';
function nzmSentOrders() {
    try {
        var sent = JSON.parse(window.localStorage.getItem(nzmSentKey) || '{}');
        return sent && typeof sent === 'object' ? sent : {};
    } catch (e) {
        return {};
    }
}
function nzmIsOrderPurchase(url) {
    var u = String(url);
    for (var i = 0; i < 3; i++) {
        try {
            var decoded = decodeURIComponent(u);
            if (decoded === u) {
                break;
            }
            u = decoded;
        } catch (e) {
            break;
        }
    }
    return u.indexOf('p[pa]=purchase') !== -1 && new RegExp('[?&]p\\[ti\\]=' + nzmOrderId + '(&|$)').test(u);
}
function nzmPurchaseDelivered() {
    var requests = window._nzm && window._nzm.stats && window._nzm.stats.requests;
    var issued = false;
    for (var i = 0; requests && i < requests.length; i++) {
        if (nzmIsOrderPurchase(requests[i])) {
            issued = true;
        }
    }
    if (!issued) {
        return false;
    }
    var pending = JSON.parse(window.localStorage.getItem('nzm_retry') || '[]');
    if (!Array.isArray(pending)) {
        return false;
    }
    for (var j = 0; j < pending.length; j++) {
        if (!pending[j] || typeof pending[j].url !== 'string' || nzmIsOrderPurchase(pending[j].url)) {
            return false;
        }
    }
    return true;
}
function nzmMarkSent() {
    try {
        if (!nzmPurchaseDelivered()) {
            return;
        }
        var sent = nzmSentOrders();
        var ids = [];
        sent[nzmOrderId] = Date.now();
        for (var id in sent) {
            if (Object.prototype.hasOwnProperty.call(sent, id)) {
                ids.push(id);
            }
        }
        ids.sort(function (a, b) { return sent[b] - sent[a]; });
        for (var k = 50; k < ids.length; k++) {
            delete sent[ids[k]];
        }
        window.localStorage.setItem(nzmSentKey, JSON.stringify(sent));
    } catch (e) {}
}
function nzmWatchDelivery(attempt) {
    var nzm = window._nzm;
    // Until t.js loads _nzm is the stub array, which the tracker then replaces.
    if (!nzm || Array.isArray(nzm)) {
        if (attempt < 600) {
            setTimeout(function () { nzmWatchDelivery(attempt + 1); }, 100);
        }
        return;
    }
    var previous = nzm.onPurchase;
    nzm.onPurchase = function () {
        nzmMarkSent();
        if (typeof previous === 'function') {
            return previous.apply(this, arguments);
        }
    };
}
var nzmAlreadySent = !!nzmSentOrders()[nzmOrderId];

JS;

        return str_replace('__ORDER_ID__', (string) (int) $orderId, $js);
    }
}
