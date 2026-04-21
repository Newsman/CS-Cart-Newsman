<?php

namespace Tygh\Addons\Newsman\Export;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Export\V1\ApiV1Exception;
use Tygh\Addons\Newsman\Logger;

abstract class AbstractRetriever implements RetrieverInterface
{
    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    public function __construct(Config $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param array $data
     * @return array
     */
    public function processListParameters($data = array())
    {
        $params = $this->processListWhereParameters($data);

        $sortFound = false;
        if (isset($data['sort'])) {
            $allowedSort = $this->getAllowedSortFields();
            if (isset($allowedSort[$data['sort']])) {
                $params['sort'] = $allowedSort[$data['sort']];
                $sortFound = true;
            } elseif (isset($data['_v1_filter_fields'])) {
                throw new ApiV1Exception(1008, 'Invalid sort field: ' . $data['sort'], 400);
            }
        }

        $params['order'] = 'ASC';
        if (isset($data['order']) && strcasecmp($data['order'], 'desc') === 0) {
            $params['order'] = 'DESC';
        }
        if (!$sortFound) {
            unset($params['sort'], $params['order']);
        }

        $defaultPageSize = isset($data['default_page_size']) ? (int) $data['default_page_size'] : 1000;
        $params['start'] = (!empty($data['start']) && $data['start'] > 0) ? (int) $data['start'] : 0;
        $params['limit'] = empty($data['limit']) ? $defaultPageSize : (int) $data['limit'];
        $params['default_page_size'] = $defaultPageSize;

        return $params;
    }

    /**
     * @param array $data
     * @return array
     */
    public function processListWhereParameters($data = array())
    {
        if (!empty($data['_v1_filter_fields'])) {
            $allowedMapping = $this->getWhereParametersMapping();
            foreach ($data['_v1_filter_fields'] as $field) {
                if (!isset($allowedMapping[$field])) {
                    throw new ApiV1Exception(1006, 'Invalid filter field: ' . $field, 400);
                }
            }
        }

        $params = array('filters' => array());
        $operators = array_keys($this->getExpressionsDefinition());
        $expressions = $this->getExpressionsDefinition(false);
        $expressionsQuoted = $this->getExpressionsDefinition(true);

        foreach ($this->getWhereParametersMapping() as $requestName => $definition) {
            if (!isset($data[$requestName])) {
                continue;
            }

            $fieldName = $definition['field'];
            $isQuoted = !empty($definition['quote']);

            if (is_array($data[$requestName]) && !empty($data[$requestName]) && is_string(key($data[$requestName]))) {
                $params['filters'][$fieldName] = array();
                foreach ($data[$requestName] as $operator => $value) {
                    if (!in_array($operator, $operators, true)) {
                        if (isset($data['_v1_filter_fields'])) {
                            throw new ApiV1Exception(1007, 'Invalid filter operator: ' . $operator, 400);
                        }
                        continue;
                    }

                    $expression = $isQuoted ? $expressionsQuoted[$operator] : $expressions[$operator];
                    $expression = str_replace(':field', $fieldName, $expression);

                    if ($operator === 'in' || $operator === 'nin') {
                        $separator = $isQuoted ? "','" : ',';
                        $expression = str_replace(
                            ':value',
                            implode($separator, $this->escapeValueForSql($value, $definition['type'])),
                            $expression
                        );
                    } else {
                        $expression = str_replace(':value', $this->escapeValueForSql($value, $definition['type']), $expression);
                    }

                    $params['filters'][$fieldName][] = $expression;
                }
            } elseif (is_array($data[$requestName]) && !empty($definition['multiple'])) {
                $value = $data[$requestName];
                $separator = $isQuoted ? "','" : ',';
                $params['filters'][$fieldName] = $fieldName . ' IN ('
                    . implode($separator, $this->escapeValueForSql($value, $definition['type'])) . ')';
            } else {
                $value = $data[$requestName];
                $params['filters'][$fieldName] = $fieldName . ' = '
                    . ($isQuoted ? "'" : '')
                    . $this->escapeValueForSql($value, $definition['type'])
                    . ($isQuoted ? "'" : '');
            }
        }

        return $params;
    }

    /**
     * @return array
     */
    public function getWhereParametersMapping()
    {
        return array();
    }

    /**
     * @return array
     */
    public function getAllowedSortFields()
    {
        return array();
    }

    /**
     * @param mixed  $value
     * @param string $type
     * @return mixed
     */
    public function escapeValueForSql($value, $type)
    {
        if (is_array($value)) {
            $return = array();
            foreach ($value as $item) {
                $return[] = $this->escapeValueForSql($item, $type);
            }

            return $return;
        }

        if ($type === 'int') {
            return (int) $value;
        }

        return addslashes((string) $value);
    }

    /**
     * @param bool $addQuotes
     * @return array
     */
    public function getExpressionsDefinition($addQuotes = true)
    {
        $value = $addQuotes ? "':value'" : ':value';

        return array(
            'eq'      => ':field = ' . $value,
            'neq'     => ':field <> ' . $value,
            'like'    => ':field LIKE ' . $value,
            'nlike'   => ':field NOT LIKE ' . $value,
            'in'      => ':field IN(' . $value . ')',
            'nin'     => ':field NOT IN(' . $value . ')',
            'is'      => ':field IS ' . $value,
            'notnull' => ':field IS NOT NULL',
            'null'    => ':field IS NULL',
            'gt'      => ':field > ' . $value,
            'lt'      => ':field < ' . $value,
            'gteq'    => ':field >= ' . $value,
            'lteq'    => ':field <= ' . $value,
            'from'    => ':field >= ' . $value,
            'to'      => ':field <= ' . $value,
        );
    }

    /**
     * @param string $where
     * @return string
     */
    public function buildFilterSql($where = '')
    {
        return $where;
    }

    /**
     * @param array $filters
     * @return string
     */
    public function filtersToSql($filters)
    {
        if (empty($filters)) {
            return '';
        }

        $parts = array();
        foreach ($filters as $fieldExpressions) {
            if (is_string($fieldExpressions)) {
                $parts[] = $fieldExpressions;
            } elseif (is_array($fieldExpressions)) {
                foreach ($fieldExpressions as $expr) {
                    $parts[] = $expr;
                }
            }
        }

        return empty($parts) ? '' : ' AND ' . implode(' AND ', $parts);
    }

    /**
     * @param string $phone
     * @return string
     */
    public function cleanPhone($phone)
    {
        $phone = trim($phone);
        if (empty($phone)) {
            return '';
        }

        $prefix = '';
        if (isset($phone[0]) && $phone[0] === '+') {
            $prefix = '+';
            $phone = substr($phone, 1);
        }

        return $prefix . preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Resolve the company IDs across every storefront that shares the same
     * Newsman list_id as this request. Used to scope company-keyed tables
     * (users / orders / products / promotions) in multi-storefront setups.
     *
     * Returns null when the request can't be tied to a specific Newsman list
     * (no list_id parameter and no list configured) — callers should treat
     * that as "no company filter" so single-storefront installs keep working
     * as before.
     *
     * @param array $data
     * @return array<int>|null
     */
    public function resolveTargetCompanyIds(array $data)
    {
        $newsmanListId = isset($data['list_id']) && $data['list_id'] !== ''
            ? (string) $data['list_id']
            : $this->config->getListId();

        if ($newsmanListId === '') {
            return null;
        }

        return $this->config->getCompanyIdsByNewsmanListId($newsmanListId);
    }

    /**
     * @return int
     */
    public function getDefaultLanguageId()
    {
        return (int) db_get_field("SELECT lang_id FROM ?:languages WHERE status = 'A' ORDER BY lang_id ASC LIMIT 1");
    }

    /**
     * @return string
     */
    public function getDefaultLanguageCode()
    {
        return (string) db_get_field("SELECT lang_code FROM ?:languages WHERE status = 'A' ORDER BY lang_id ASC LIMIT 1");
    }
}
