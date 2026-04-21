<?php

namespace Tygh\Addons\Newsman\Remarketing;

class CustomerIdentifyDeferred
{
    /**
     * Render a static bootstrap script that resolves the logged-in customer
     * client-side by fetching a non-cacheable JSON endpoint and calling
     * `_nzm.run('identify', ...)` with the response.
     *
     * The HTML contains no per-user data, so the surrounding page stays
     * safely cacheable by Varnish / full_page_cache.
     *
     * @param string $identifyUrl Absolute URL of the newsman_front.identify endpoint.
     * @return string
     */
    public function getHtml($identifyUrl)
    {
        if (empty($identifyUrl)) {
            return '';
        }

        $urlJs = JsHelper::escapeJs($identifyUrl);

        $js = <<<JS
(function () {
    try {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', '{$urlJs}', true);
        xhr.withCredentials = true;
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.onload = function () {
            if (xhr.status !== 200) { return; }
            var data;
            try { data = JSON.parse(xhr.responseText); } catch (e) { return; }
            if (!data || !data.email) { return; }
            _nzm.run('identify', {
                'email': data.email,
                'first_name': data.first_name || '',
                'last_name': data.last_name || ''
            });
        };
        xhr.send(null);
    } catch (e) {}
})();
JS;

        return JsHelper::wrapScript($js);
    }
}
