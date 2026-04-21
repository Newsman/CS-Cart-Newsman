<?php

namespace Tygh\Addons\Newsman\Remarketing;

class ProductView
{
    /**
     * @param int    $productId
     * @param string $productName
     * @param string $category
     * @param string $price
     * @return string
     */
    public function getHtml($productId, $productName = '', $category = '', $price = '')
    {
        $idJs = (int) $productId;
        $nameJs = JsHelper::escapeJs($productName);
        $catJs = JsHelper::escapeJs($category);

        $js = "_nzm.run('ec:addProduct', {"
            . "'id': '{$idJs}'"
            . ", 'name': '{$nameJs}'";

        if (!empty($catJs)) {
            $js .= ", 'category': '{$catJs}'";
        }

        if (!empty($price)) {
            $js .= ", 'price': '" . number_format((float) $price, 2, '.', '') . "'";
        }

        $js .= "});\n";
        $js .= "_nzm.run('ec:setAction', 'detail');\n";

        return JsHelper::wrapScript($js);
    }
}
