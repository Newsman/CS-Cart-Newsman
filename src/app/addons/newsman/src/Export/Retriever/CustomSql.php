<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use PHPSQLParser\PHPSQLParser;
use Tygh\Addons\Newsman\Export\AbstractRetriever;
use Tygh\Addons\Newsman\Export\V1\ApiV1Exception;

class CustomSql extends AbstractRetriever
{
    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        $sql = isset($data['sql']) ? trim((string) $data['sql']) : '';

        if ($sql === '') {
            throw new ApiV1Exception(6001, 'Missing "sql" parameter', 400);
        }

        $this->validateSelectOnly($sql);

        $sql = $this->replaceTablePlaceholders($sql);

        $this->logger->notice(sprintf('Custom SQL export - Query: %s', $sql));

        try {
            $rows = db_get_array($sql);
        } catch (\Exception $e) {
            throw new ApiV1Exception(6006, 'SQL query execution failed: ' . $e->getMessage(), 500);
        }

        $rows = is_array($rows) ? $rows : array();
        $this->logger->notice(sprintf('Custom SQL export - Rows returned: %d', count($rows)));

        return $rows;
    }

    /**
     * @param string $sql
     */
    protected function validateSelectOnly($sql)
    {
        $this->validateNoMultipleStatements($sql);

        $parser = new PHPSQLParser();
        $parsed = $parser->parse($sql);

        if (empty($parsed)) {
            throw new ApiV1Exception(6005, 'Unable to parse the SQL query', 400);
        }

        $statementType = key($parsed);

        if ($statementType !== 'SELECT') {
            throw new ApiV1Exception(6002, 'Only SELECT queries are allowed. Got: ' . $statementType, 400);
        }

        if (isset($parsed['INTO'])) {
            throw new ApiV1Exception(6003, 'SELECT INTO is not allowed', 400);
        }
    }

    /**
     * @param string $sql
     */
    protected function validateNoMultipleStatements($sql)
    {
        $stripped = preg_replace("/'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'/s", '', $sql);
        $stripped = preg_replace('/"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"/s', '', $stripped);

        if (strpos($stripped, ';') !== false) {
            throw new ApiV1Exception(6004, 'Multiple statements are not allowed', 400);
        }
    }

    /**
     * Replace {table_name} placeholders with CS-Cart's ?:table_name prefix marker.
     *
     * @param string $sql
     * @return string
     */
    protected function replaceTablePlaceholders($sql)
    {
        return preg_replace_callback(
            '/\{([a-zA-Z0-9_]+)\}/',
            function ($matches) {
                return '?:' . $matches[1];
            },
            $sql
        );
    }
}
