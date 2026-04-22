<?php

namespace Tygh\Addons\Newsman\Action\Order;

class StatusMapper
{
    /** @var array<string, string> */
    protected static $cache = array();

    /** @var array<string, string> */
    protected static $knownMap = array(
        'P' => 'payment',
        'C' => 'complete',
        'O' => 'pending',
        'F' => 'payment_error',
        'D' => 'canceled',
        'B' => 'pending',
        'I' => 'canceled',
        'N' => 'new',
    );

    /**
     * @param string $cscartStatus
     * @return string
     */
    public function toNewsman($cscartStatus)
    {
        $code = (string) $cscartStatus;

        if (isset(self::$cache[$code])) {
            return self::$cache[$code];
        }

        if (isset(self::$knownMap[$code])) {
            return self::$cache[$code] = self::$knownMap[$code];
        }

        $slug = $this->slugify($code);

        return self::$cache[$code] = $slug;
    }

    /**
     * @param string $cscartStatus
     * @return string
     */
    protected function slugify($cscartStatus)
    {
        $langCode = defined('CART_LANGUAGE') ? CART_LANGUAGE : (defined('DEFAULT_LANGUAGE') ? DEFAULT_LANGUAGE : 'en');

        $name = db_get_field(
            "SELECT sd.description"
            . " FROM ?:statuses AS s"
            . " LEFT JOIN ?:status_descriptions AS sd"
            . " ON sd.status_id = s.status_id AND sd.lang_code = ?s"
            . " WHERE s.status = ?s AND s.type = ?s"
            . " LIMIT 1",
            $langCode,
            $cscartStatus,
            'O'
        );

        if (empty($name) && $langCode !== 'en') {
            $name = db_get_field(
                "SELECT sd.description"
                . " FROM ?:statuses AS s"
                . " LEFT JOIN ?:status_descriptions AS sd"
                . " ON sd.status_id = s.status_id AND sd.lang_code = ?s"
                . " WHERE s.status = ?s AND s.type = ?s"
                . " LIMIT 1",
                'en',
                $cscartStatus,
                'O'
            );
        }

        if (empty($name)) {
            return 'status_' . strtolower($cscartStatus);
        }

        $slug = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim((string) $slug, '_');

        return $slug !== '' ? $slug : 'status_' . strtolower($cscartStatus);
    }
}
