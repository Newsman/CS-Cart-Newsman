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
     * Additionally, once per browser session (gated by the nzm_cart_sync
     * cookie), fires an explicit clear_cart when the storefront cart is
     * empty — otherwise the equal-JSON early-return in processCart() would
     * leave a stale Newsman-side cart untouched. When the cart is non-empty
     * the regular processCart() sync already handles it; bootstrap only
     * sets the cookie in that branch.
     *
     * @param string $cookiePath Cookie path to scope the session flag to the
     *                           current storefront's base URL.
     * @return string
     */
    public function getHtml($cookiePath = '/')
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

    function nzmGetCookie(name) {
        var needle = name + '=';
        var parts = document.cookie ? document.cookie.split(';') : [];
        for (var i = 0; i < parts.length; i++) {
            var p = parts[i].replace(/^\s+/, '');
            if (p.indexOf(needle) === 0) {
                return p.substring(needle.length);
            }
        }
        return null;
    }

    function nzmSetSessionCookie(name, value, path) {
        // No Max-Age / Expires => browser-session lifetime. SameSite=Lax keeps
        // it on ordinary same-site navigations. Cleared when the browser closes.
        document.cookie = name + '=' + value + '; path=' + path + '; SameSite=Lax';
    }

    // Once per browser session (gated by nzm_cart_sync cookie), handle the
    // edge case where the storefront cart is empty but the Newsman-side
    // remarketing cart still holds items from a previous session. processCart
    // early-returns when both lastCart and payload are empty, so without this
    // hook the stale Newsman cart is never cleared. For a non-empty payload
    // we skip the clear — processCart() will run immediately after and emit
    // its own clear_cart + add sequence.
    function nzmSessionBootstrap() {
        var cookieName = 'nzm_cart_sync';
        var cookiePath = __NEWSMAN_COOKIE_PATH__;
        if (nzmGetCookie(cookieName)) {
            return;
        }
        var payload = readPayload();
        if (payload !== null && payload.length === 0) {
            nzmClearCart();
        }
        nzmSetSessionCookie(cookieName, '1', cookiePath);
    }

    function boot() {
        startObserver();
        nzmSessionBootstrap();
        processCart();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
JS;

        $js = strtr($js, array(
            '__NEWSMAN_COOKIE_PATH__' => json_encode(
                $cookiePath,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES
            ),
        ));

        return JsHelper::wrapScript($js);
    }
}
