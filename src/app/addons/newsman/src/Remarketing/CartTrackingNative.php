<?php

namespace Tygh\Addons\Newsman\Remarketing;

class CartTrackingNative
{
    /**
     * Generate the native (no-polling) cart tracking JS HTML.
     *
     * Reads the cart JSON rendered by the Newsman minicart hook and uses a
     * MutationObserver so every time CS-Cart re-renders the cart_status_*
     * block via cm-ajax-full-render the new JSON is picked up automatically —
     * no periodic polling and no call to the addon's own cart endpoint.
     *
     * The payload is stored in a `data-newsman-cart` attribute on a hidden
     * <span> rather than a `<script type="application/json">`, because
     * CS-Cart's AJAX response handler (js/tygh/ajax.js) strips every
     * <script> tag out of injected HTML before calling $.html() — so a
     * script-based carrier would never update after an add-to-cart.
     *
     * @return string
     */
    public function getHtml()
    {
        $js = <<<'JS'
_nzm.run('require', 'ec');

(function () {
    var isProd = true;
    var nzmCartSelector = '[data-newsman-cart]';

    function readPayload() {
        var node = document.querySelector(nzmCartSelector);
        if (!node) {
            return null;
        }
        var raw = node.getAttribute('data-newsman-cart');
        if (raw === null) {
            raw = (node.textContent || '').trim();
        }
        if (raw === '') {
            return [];
        }
        try {
            var data = JSON.parse(raw);
            return Array.isArray(data) ? data : [];
        } catch (e) {
            if (!isProd) {
                console.log('newsman remarketing: cannot parse minicart JSON');
            }
            return null;
        }
    }

    function readLastCart() {
        try {
            var raw = sessionStorage.getItem('lastCart');
            if (raw === null || raw === '') {
                return [];
            }
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function writeLastCart(products) {
        try {
            sessionStorage.setItem('lastCart', JSON.stringify(products));
        } catch (e) {}
    }

    function nzmClearCart() {
        _nzm.run('ec:setAction', 'clear_cart');
        _nzm.run('send', 'event', 'detail view', 'click', 'clearCart');
        writeLastCart([]);
        if (!isProd) {
            console.log('newsman remarketing: clear cart sent');
        }
    }

    function nzmAddToCart(products) {
        _nzm.run('ec:setAction', 'clear_cart');
        _nzm.run('send', 'event', 'detail view', 'click', 'clearCart', null, function () {
            var sent = [];
            for (var i = 0; i < products.length; i++) {
                var item = products[i];
                if (item && item.hasOwnProperty('id')) {
                    // Pass a fresh copy to _nzm — Newsman's send pipeline mutates
                    // queued product objects, which would otherwise strip "name"
                    // from the entry we persist to sessionStorage.
                    _nzm.run('ec:addProduct', {
                        id: item.id,
                        name: item.name,
                        price: item.price,
                        quantity: item.quantity
                    });
                    sent.push({
                        id: item.id,
                        name: item.name,
                        price: item.price,
                        quantity: item.quantity
                    });
                }
            }
            _nzm.run('ec:setAction', 'add');
            _nzm.run('send', 'event', 'UX', 'click', 'add to cart');
            writeLastCart(sent);
            if (!isProd) {
                console.log('newsman remarketing: cart sent');
            }
        });
    }

    function processCart() {
        var products = readPayload();
        if (products === null) {
            return;
        }
        var lastCart = readLastCart();
        var lastJson = JSON.stringify(lastCart);
        var nextJson = JSON.stringify(products);

        if (lastJson === nextJson) {
            if (!isProd) {
                console.log('newsman remarketing: minicart unchanged');
            }
            return;
        }

        if (products.length === 0) {
            if (lastCart.length > 0) {
                nzmClearCart();
            } else {
                writeLastCart([]);
            }
            return;
        }

        nzmAddToCart(products);
    }

    var scheduled = false;
    function scheduleProcess() {
        if (scheduled) {
            return;
        }
        scheduled = true;
        setTimeout(function () {
            scheduled = false;
            processCart();
        }, 50);
    }

    function startObserver() {
        if (typeof MutationObserver !== 'function') {
            return;
        }
        var target = document.body || document.documentElement;
        if (!target) {
            return;
        }
        var observer = new MutationObserver(function () {
            scheduleProcess();
        });
        observer.observe(target, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }

    function boot() {
        startObserver();
        processCart();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
JS;

        return JsHelper::wrapScript($js);
    }
}
