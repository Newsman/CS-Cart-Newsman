<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Export\AbstractRetriever;
use Tygh\Addons\Newsman\Export\V1\ApiV1Exception;

class Coupons extends AbstractRetriever
{
    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        $this->logger->info(sprintf('Add coupons: %s', json_encode($data)));

        if (!isset($data['type'])) {
            throw new ApiV1Exception(8001, 'Missing "type" parameter', 400);
        }
        $type = (int) $data['type'];
        if (!in_array($type, array(0, 1), true)) {
            throw new ApiV1Exception(8002, 'Invalid "type" parameter: must be 0 (fixed) or 1 (percent)', 400);
        }

        if (!isset($data['value'])) {
            throw new ApiV1Exception(8003, 'Missing "value" parameter', 400);
        }
        $value = (float) $data['value'];
        if ($value <= 0) {
            throw new ApiV1Exception(8004, 'Invalid "value" parameter: must be greater than 0', 400);
        }

        $prefix = isset($data['prefix']) ? $data['prefix'] : '';
        $batchSize = isset($data['batch_size']) ? (int) $data['batch_size'] : 1;
        $expireDate = isset($data['expire_date']) ? $data['expire_date'] : '';
        $minAmount = isset($data['min_amount']) ? (float) $data['min_amount'] : 0;

        if ($batchSize < 1) {
            throw new ApiV1Exception(8005, 'Invalid "batch_size" parameter: must be >= 1', 400);
        }
        $batchSize = min($batchSize, 100);

        if (!empty($expireDate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expireDate)) {
            throw new ApiV1Exception(8006, 'Invalid "expire_date" format: expected YYYY-MM-DD', 400);
        }

        if ($minAmount < 0) {
            $minAmount = 0;
        }

        // Multistore: when several storefronts share the same Newsman list_id,
        // create one promotion row per company so each storefront has its own
        // local copy of the coupon. With one storefront (or no list resolved)
        // this falls back to a single promotion at the request's scope.
        $companyIds = $this->resolveTargetCompanyIds($data);
        if (!is_array($companyIds) || empty($companyIds)) {
            $companyIds = array(null);
        }

        $codes = array();
        for ($i = 0; $i < $batchSize; $i++) {
            $code = $this->generateUniqueCouponCode($prefix);

            $bonusType = ($type === 1) ? 'to_percentage' : 'by_fixed';

            $conditions = array(
                'set' => 'all',
                'set_value' => 1,
                'conditions' => array(
                    array(
                        'condition' => 'coupon_code',
                        'operator'  => 'eq',
                        'value'     => $code,
                    ),
                ),
            );

            if ($minAmount > 0) {
                $conditions['conditions'][] = array(
                    'condition' => 'subtotal',
                    'operator'  => 'gteq',
                    'value'     => $minAmount,
                );
            }

            $bonuses = array(
                array(
                    'bonus'          => 'order_discount',
                    'discount_bonus' => $bonusType,
                    'discount_value' => $value,
                ),
            );

            $toDate = 0;
            if (!empty($expireDate)) {
                $toDate = strtotime($expireDate);
            }
            if (empty($toDate)) {
                $toDate = strtotime('+5 year');
            }

            $basePromotionData = array(
                'zone'       => 'cart',
                'status'     => 'A',
                'conditions' => serialize($conditions),
                'bonuses'    => serialize($bonuses),
                'from_date'  => TIME,
                'to_date'    => $toDate,
                'priority'   => 0,
                'stop'       => 'N',
            );

            foreach ($companyIds as $companyId) {
                $promotionData = $basePromotionData;
                if ($companyId !== null) {
                    $promotionData['company_id'] = (int) $companyId;
                }

                $promotionId = db_query("INSERT INTO ?:promotions ?e", $promotionData);

                if ($promotionId) {
                    $descData = array(
                        'promotion_id' => $promotionId,
                        'lang_code'    => CART_LANGUAGE,
                        'name'         => 'Newsman: ' . $code,
                    );
                    db_query("INSERT INTO ?:promotion_descriptions ?e", $descData);
                }
            }

            $codes[] = $code;
        }

        $this->logger->info(sprintf('Added %d coupons %s', count($codes), implode(', ', $codes)));

        return array('status' => 1, 'codes' => $codes);
    }

    /**
     * @param string $prefix
     * @return string
     */
    public function generateUniqueCouponCode($prefix = '')
    {
        $maxRetries = 3;
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $code = $this->generateCouponCode($prefix);

            // Check uniqueness in promotions conditions
            $exists = db_get_field(
                "SELECT promotion_id FROM ?:promotions WHERE conditions LIKE ?l LIMIT 1",
                '%' . $code . '%'
            );

            if (empty($exists)) {
                return $code;
            }
        }

        // If all retries fail, still return the last code
        return $this->generateCouponCode($prefix);
    }

    /**
     * @param string $prefix
     * @return string
     */
    public function generateCouponCode($prefix = '')
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $len = strlen($chars);
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            if (function_exists('random_int')) {
                $code .= $chars[random_int(0, $len - 1)];
            } else {
                $code .= $chars[mt_rand(0, $len - 1)];
            }
        }

        return !empty($prefix) ? $prefix . $code : $code;
    }
}
