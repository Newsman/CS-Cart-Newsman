<?php

namespace Tygh\Addons\Newsman\Export;

class PayloadParser
{
    /**
     * @var array
     */
    /**
     * @var array
     */
    public static $methodMap = array(
        'customer.list'            => 'customers',
        'subscriber.list'          => 'subscribers',
        'subscriber.subscribe'     => 'subscriber-subscribe',
        'subscriber.unsubscribe'   => 'subscriber-unsubscribe',
        'product.list'             => 'products-feed',
        'order.list'               => 'orders',
        'coupon.create'            => 'coupons',
        'custom.sql'               => 'custom-sql',
        'platform.name'            => 'platform-name',
        'platform.version'         => 'platform-version',
        'platform.language'        => 'platform-language',
        'platform.language_version' => 'platform-language-version',
        'integration.name'         => 'integration-name',
        'integration.version'      => 'integration-version',
        'server.ip'                => 'server-ip',
        'server.cloudflare'        => 'server-cloudflare',
        'sql.name'                 => 'sql-name',
        'sql.version'              => 'sql-version',
        'refresh.remarketing'      => 'refresh-remarketing',
    );

    /**
     * @param string $rawBody
     * @return array
     * @throws \Tygh\Addons\Newsman\Export\V1\ApiV1Exception
     */
    public function parse($rawBody)
    {
        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            throw new \Tygh\Addons\Newsman\Export\V1\ApiV1Exception(1002, 'Invalid JSON payload', 400);
        }

        if (empty($payload['method'])) {
            throw new \Tygh\Addons\Newsman\Export\V1\ApiV1Exception(1003, 'Missing "method" field', 400);
        }

        $method = $payload['method'];

        if (!isset(self::$methodMap[$method])) {
            throw new \Tygh\Addons\Newsman\Export\V1\ApiV1Exception(1004, 'Unknown method: ' . $method, 404);
        }

        $params = isset($payload['params']) ? $payload['params'] : array();

        if (!is_array($params)) {
            throw new \Tygh\Addons\Newsman\Export\V1\ApiV1Exception(1005, '"params" must be a JSON object', 400);
        }

        $filterFields = array();
        if (isset($params['filter']) && is_array($params['filter'])) {
            foreach ($params['filter'] as $fieldName => $fieldValue) {
                $params[$fieldName] = $fieldValue;
                $filterFields[] = $fieldName;
            }
            unset($params['filter']);
        }
        $params['_v1_filter_fields'] = $filterFields;

        return array(
            'method'         => $method,
            'retriever_code' => self::$methodMap[$method],
            'params'         => $params,
        );
    }

    /**
     * @param string $rawBody
     * @return bool
     */
    public function isJsonPayload($rawBody)
    {
        if (empty($rawBody)) {
            return false;
        }

        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        if (strpos($contentType, 'application/json') !== false) {
            return true;
        }

        $trimmed = ltrim($rawBody);
        return isset($trimmed[0]) && $trimmed[0] === '{';
    }
}
