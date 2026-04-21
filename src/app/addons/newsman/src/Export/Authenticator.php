<?php

namespace Tygh\Addons\Newsman\Export;

use Tygh\Addons\Newsman\Config;

class Authenticator
{
    /** @var Config */
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return bool
     */
    public function authenticate()
    {
        $providedKey = $this->extractKey();

        if (empty($providedKey)) {
            return false;
        }

        $apiKey = $this->config->getApiKey();
        $authToken = $this->config->getAuthenticateToken();
        $alternateKey = $this->config->getExportAuthHeaderKey();

        if (!empty($apiKey) && hash_equals($apiKey, $providedKey)) {
            return true;
        }

        if (!empty($authToken) && hash_equals($authToken, $providedKey)) {
            return true;
        }

        $alternateName = $this->config->getExportAuthHeaderName();
        if (!empty($alternateName) && !empty($alternateKey) && hash_equals($alternateKey, $providedKey)) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function extractKey()
    {
        // Authorization: Bearer <key>
        $authHeader = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        }

        if (!empty($authHeader) && stripos($authHeader, 'Bearer ') === 0) {
            return trim(substr($authHeader, 7));
        }

        // Custom export auth header
        $customHeaderName = $this->config->getExportAuthHeaderName();
        if (!empty($customHeaderName)) {
            $customValue = $this->getHeaderValue($customHeaderName);
            if (!empty($customValue)) {
                return $customValue;
            }
        }

        // Legacy query-string fallback
        if (isset($_REQUEST['nzmhash'])) {
            return $_REQUEST['nzmhash'];
        }

        return '';
    }

    /**
     * @param string $name
     * @return string
     */
    public function getHeaderValue($name)
    {
        // Try $_SERVER with HTTP_ prefix
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKey])) {
            return trim($_SERVER[$serverKey]);
        }

        // Try apache_request_headers
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $key => $value) {
                if (strcasecmp($key, $name) === 0) {
                    return trim($value);
                }
            }
        }

        return '';
    }
}
