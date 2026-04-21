<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Export\AbstractRetriever;
use Tygh\Registry;

class Products extends AbstractRetriever
{
    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        $this->logger->info('Export products');

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
        $data['default_page_size'] = 1000;
        $params = $this->processListParameters($data);
        $langCode = CART_LANGUAGE;
        $filterSql = $this->filtersToSql($params['filters']);

        // Multistore: only return products owned by companies whose storefronts
        // share the request's Newsman list_id.
        $companyIds = $this->resolveTargetCompanyIds($data);
        $companyCondition = '';
        if (is_array($companyIds)) {
            if (empty($companyIds)) {
                return array();
            }
            $companyCondition = db_quote(' AND p.company_id IN (?n)', $companyIds);
        }

        // ULT per-storefront name/price overrides scoped to the company of the
        // storefront actually serving this request — when two storefronts share
        // a Newsman list, the requesting storefront's overrides win.
        $useUlt = function_exists('fn_allowed_for') && fn_allowed_for('ULTIMATE');
        $runtimeCompanyId = $useUlt && function_exists('fn_get_runtime_company_id')
            ? (int) fn_get_runtime_company_id()
            : 0;

        $selectName = 'pd.product AS name';
        $selectPrice = 'pp.price';
        $ultJoins = '';
        if ($useUlt && $runtimeCompanyId > 0) {
            $selectName = "COALESCE(NULLIF(upd.product, ''), pd.product) AS name";
            $selectPrice = 'COALESCE(upp.price, pp.price) AS price';
            $ultJoins = db_quote(
                ' LEFT JOIN ?:ult_product_descriptions AS upd'
                . ' ON upd.product_id = p.product_id AND upd.lang_code = ?s AND upd.company_id = ?i'
                . ' LEFT JOIN ?:ult_product_prices AS upp'
                . ' ON upp.product_id = p.product_id AND upp.lower_limit = 1 AND upp.usergroup_id = 0 AND upp.company_id = ?i',
                $langCode,
                $runtimeCompanyId,
                $runtimeCompanyId
            );
        }

        $orderSql = isset($params['sort'])
            ? ' ORDER BY ' . $params['sort'] . ' ' . $params['order']
            : ' ORDER BY p.product_id ASC';

        $products = db_get_array(
            "SELECT p.product_id, " . $selectName . ", pd.short_description,"
            . ' ' . $selectPrice . ', p.list_price, p.product_code AS sku, p.amount AS quantity,'
            . " p.status, p.timestamp, p.updated_timestamp"
            . " FROM ?:products AS p"
            . " LEFT JOIN ?:product_descriptions AS pd ON p.product_id = pd.product_id AND pd.lang_code = ?s"
            . " LEFT JOIN ?:product_prices AS pp ON p.product_id = pp.product_id AND pp.lower_limit = 1 AND pp.usergroup_id = 0"
            . $ultJoins
            . " WHERE p.status = 'A'" . $companyCondition . $filterSql
            . $orderSql
            . " LIMIT ?i, ?i",
            $langCode,
            $params['start'],
            $params['limit']
        );

        if (empty($products)) {
            return array();
        }

        // Pre-load categories
        $productIds = array();
        foreach ($products as $product) {
            $productIds[] = $product['product_id'];
        }
        $categoryMap = $this->loadProductCategories($productIds, $langCode);

        $result = array();
        foreach ($products as $product) {
            $pid = $product['product_id'];
            $imageUrl = $this->getProductImageUrl($pid, $langCode);
            $productUrl = fn_url('products.view?product_id=' . $pid, 'C');

            $price = (float) $product['price'];
            $listPrice = (float) $product['list_price'];
            $hasDiscount = $listPrice > 0 && $listPrice > $price;

            $categories = isset($categoryMap[$pid]) ? $categoryMap[$pid] : array();
            $categoryPath = $this->getDeepestCategoryPath($categories);

            $item = array(
                'id'             => (string) $pid,
                'url'            => $productUrl,
                'name'           => $product['name'],
                'price'          => number_format($price, 2, '.', ''),
                'price_old'      => $hasDiscount ? number_format($listPrice, 2, '.', '') : '',
                'image_url'      => $imageUrl,
                'category'       => !empty($categoryPath) ? $categoryPath[0] : '',
                'subcategories'  => !empty($categories) ? $categories : array(),
                'in_stock'       => ((int) $product['quantity'] > 0) ? 1 : 0,
                'stock_quantity' => (int) $product['quantity'],
                'variants'       => '',
                'sku'            => $product['sku'],
            );

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array  $productIds
     * @param string $langCode
     * @return array
     */
    public function loadProductCategories($productIds, $langCode)
    {
        if (empty($productIds)) {
            return array();
        }

        $rows = db_get_array(
            "SELECT pc.product_id, pc.category_id"
            . " FROM ?:products_categories AS pc"
            . " WHERE pc.product_id IN (?n)",
            $productIds
        );

        if (empty($rows)) {
            return array();
        }

        // Get all unique category IDs
        $catIds = array();
        foreach ($rows as $row) {
            $catIds[] = $row['category_id'];
        }
        $catIds = array_unique($catIds);

        // Load category names and parent chain
        $allCategories = db_get_hash_array(
            "SELECT c.category_id, c.parent_id, cd.category AS name"
            . " FROM ?:categories AS c"
            . " LEFT JOIN ?:category_descriptions AS cd ON c.category_id = cd.category_id AND cd.lang_code = ?s"
            . " WHERE c.status = 'A'",
            'category_id',
            $langCode
        );

        // Build category paths for each product
        $result = array();
        foreach ($rows as $row) {
            $path = $this->buildCategoryPath($row['category_id'], $allCategories);
            if (!empty($path)) {
                $result[$row['product_id']][] = $path;
            }
        }

        return $result;
    }

    /**
     * @param int   $categoryId
     * @param array $allCategories
     * @return array
     */
    public function buildCategoryPath($categoryId, $allCategories)
    {
        $path = array();
        $seen = array();
        $currentId = $categoryId;

        while ($currentId > 0 && isset($allCategories[$currentId]) && !isset($seen[$currentId])) {
            $seen[$currentId] = true;
            $cat = $allCategories[$currentId];
            if (!empty($cat['name'])) {
                array_unshift($path, html_entity_decode($cat['name'], ENT_QUOTES, 'UTF-8'));
            }
            $currentId = (int) $cat['parent_id'];
        }

        return $path;
    }

    /**
     * @param array $categories
     * @return array
     */
    public function getDeepestCategoryPath($categories)
    {
        if (empty($categories)) {
            return array();
        }

        $deepest = array();
        foreach ($categories as $path) {
            if (count($path) > count($deepest)) {
                $deepest = $path;
            }
        }

        return $deepest;
    }

    /**
     * @param int    $productId
     * @param string $langCode
     * @return string
     */
    public function getProductImageUrl($productId, $langCode)
    {
        $pair = fn_get_image_pairs($productId, 'product', 'M', true, true, $langCode);

        if (!empty($pair['detailed']['image_path'])) {
            return $pair['detailed']['image_path'];
        }

        if (!empty($pair['icon']['image_path'])) {
            return $pair['icon']['image_path'];
        }

        return rtrim((string) Registry::get('config.current_location'), '/') . '/images/no_image.png';
    }

    /**
     * @return array
     */
    public function getWhereParametersMapping()
    {
        return array(
            'created_at'  => array('field' => 'p.timestamp', 'quote' => false, 'type' => 'int'),
            'modified_at' => array('field' => 'p.updated_timestamp', 'quote' => false, 'type' => 'int'),
            'product_id'  => array('field' => 'p.product_id', 'quote' => false, 'type' => 'int'),
            'product_ids' => array('field' => 'p.product_id', 'quote' => false, 'type' => 'int', 'multiple' => true),
        );
    }

    /**
     * @return array
     */
    public function getAllowedSortFields()
    {
        return array(
            'created_at'  => 'p.timestamp',
            'modified_at' => 'p.updated_timestamp',
            'product_id'  => 'p.product_id',
            'name'        => 'pd.product',
            'price'       => 'pp.price',
        );
    }
}
