<?php

namespace Tygh\Addons\Newsman\Remarketing;

class CategoryView
{
    /**
     * @param int   $categoryId
     * @param array $products
     * @param string $listName
     * @return string
     */
    public function getHtml($categoryId, $products = array(), $listName = '')
    {
        if (empty($products)) {
            return '';
        }

        $listJs = JsHelper::escapeJs($listName);

        $js = '';
        $position = 0;
        foreach ($products as $product) {
            $position++;
            $pid = isset($product['product_id']) ? (int) $product['product_id'] : 0;
            $name = isset($product['product']) ? JsHelper::escapeJs($product['product']) : '';
            $category = isset($product['category']) ? JsHelper::escapeJs($product['category']) : '';
            $price = isset($product['price']) ? number_format((float) $product['price'], 2, '.', '') : '';

            $js .= "_nzm.run('ec:addImpression', {"
                . "'id': '{$pid}'"
                . ", 'name': '{$name}'";

            if (!empty($category)) {
                $js .= ", 'category': '{$category}'";
            }

            if (!empty($price)) {
                $js .= ", 'price': '{$price}'";
            }

            if (!empty($listJs)) {
                $js .= ", 'list': '{$listJs}'";
            }

            $js .= ", 'position': " . $position
                . "});\n";
        }

        return JsHelper::wrapScript($js);
    }
}
