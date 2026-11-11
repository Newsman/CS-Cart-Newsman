<?php

namespace Tygh\Addons\Newsman\Api;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

class Client
{
    /** Error code when Newsman could not be reached (no HTTP answer). */
    const ERROR_CODE_NO_RESPONSE = 599;

    /** Error code for an error answer at HTTP 200 that carries no Newsman code. */
    const ERROR_CODE_GENERIC = 1;

    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /**
     * @param Config $config
     * @param Logger $logger
     */
    public function __construct(Config $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @return Context
     */
    public function createContext()
    {
        $context = new Context();
        $context->setUserId($this->config->getUserId())
                ->setApiKey($this->config->getApiKey())
                ->setListId($this->config->getListId())
                ->setSegmentId($this->config->getSegmentId());

        return $context;
    }

    /**
     * @param Context $context
     * @param array   $params
     * @return array
     */
    public function get(Context $context, $params = array())
    {
        return $this->request($context, 'GET', $params);
    }

    /**
     * @param Context $context
     * @param array   $getParams
     * @param array   $postParams
     * @return array
     */
    public function post(Context $context, $getParams = array(), $postParams = array())
    {
        return $this->request($context, 'POST', $getParams, $postParams);
    }

    /**
     * @param Context $context
     * @param string  $method
     * @param array   $getParams
     * @param array   $postParams
     * @return array
     */
    public function request(Context $context, $method, $getParams = array(), $postParams = array())
    {
        $url = $context->buildUrl();

        if (!empty($getParams)) {
            $url .= '?' . http_build_query($getParams);
        }

        $logUrl = str_replace($context->getApiKey(), '****', $url);
        $logHash = uniqid();
        $this->logger->debug(sprintf('[%s] %s %s', $logHash, $method, $logUrl));

        if ($method === 'POST' && !empty($postParams)) {
            $this->logger->debug(sprintf('[%s] POST params: %s', $logHash, json_encode($postParams)));
        }

        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->getApiTimeout());
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postParams));
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = (string) curl_error($ch);
        curl_close($ch);

        $elapsedMs = round((microtime(true) - $startTime) * 1000);
        $this->logger->debug(sprintf('[%s] Requested in %s', $logHash, $this->formatTimeDuration($elapsedMs)));

        if ($response === false || $curlError !== '' || $httpCode === 0) {
            $message = $curlError !== '' ? $curlError : 'No response from Newsman';
            $this->logger->error(sprintf('[%s] cURL error: %s', $logHash, $message));
            return $this->errorResult($message, self::ERROR_CODE_NO_RESPONSE, $message);
        }

        $this->logger->debug(sprintf('[%s] Raw response (HTTP %d): %s', $logHash, $httpCode, substr($response, 0, 500)));

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $this->logger->error(sprintf('[%s] %d | %s', $logHash, $httpCode, substr($response, 0, 500)));
            $errorMsg = isset($decoded['err']) ? $decoded['err'] : "HTTP $httpCode";
            return $this->errorResult(
                $errorMsg,
                $this->errorCode(isset($decoded['code']) ? $decoded['code'] : 0, $httpCode),
                isset($decoded['message']) ? (string) $decoded['message'] : ''
            );
        }

        if ($decoded === null) {
            $this->logger->error(sprintf('[%s] Invalid JSON: %s', $logHash, substr($response, 0, 200)));
            return $this->errorResult('Invalid JSON response', $this->errorCode(0, $httpCode), 'Invalid JSON response');
        }

        $apiError = $this->parseApiError($decoded);
        if ($apiError !== false) {
            $this->logger->warning(sprintf('[%s] %d | %s', $logHash, $apiError['code'], $apiError['message']));
            return $this->errorResult(
                $apiError['message'],
                $this->errorCode($apiError['code'], $httpCode),
                $apiError['message']
            );
        }

        $this->logger->notice(sprintf('[%s] %s', $logHash, json_encode($decoded)));

        return $decoded;
    }

    /**
     * Readable text of an error result: the Newsman message, else the error.
     *
     * @param array $result Error result returned by request()
     * @return string
     */
    public static function errorMessage($result)
    {
        if (!is_array($result)) {
            return '';
        }
        if (isset($result['message']) && (string) $result['message'] !== '') {
            return (string) $result['message'];
        }
        if (isset($result['error']) && is_string($result['error']) && $result['error'] !== '') {
            return $result['error'];
        }

        return isset($result['code']) ? 'error code ' . (int) $result['code'] : 'unknown error';
    }

    /**
     * Every error result has a non-empty error, a non-zero code and a message
     * (empty when Newsman sent none).
     *
     * @param mixed  $error
     * @param int    $code
     * @param string $message
     * @return array
     */
    protected function errorResult($error, $code, $message)
    {
        return array(
            'error'   => ($error === null || $error === false || $error === '') ? 'error code ' . (int) $code : $error,
            'code'    => (int) $code,
            'message' => (string) $message,
        );
    }

    /**
     * The Newsman error code, else the HTTP status, else 1 (HTTP 200).
     *
     * @param mixed $apiCode
     * @param int   $httpCode
     * @return int
     */
    protected function errorCode($apiCode, $httpCode)
    {
        if ((int) $apiCode !== 0) {
            return (int) $apiCode;
        }
        if ($httpCode > 0 && $httpCode !== 200) {
            return $httpCode;
        }

        return self::ERROR_CODE_GENERIC;
    }

    /**
     * Parse Newsman API error from response.
     *
     * @param mixed $result Decoded JSON response
     * @return array|false Error array with 'code' and 'message', or false if not an error
     */
    public function parseApiError($result)
    {
        if (!(is_array($result) && isset($result['err']))) {
            return false;
        }

        return array(
            'code' => isset($result['code']) ? (int) $result['code'] : 0,
            'message' => isset($result['message']) ? $result['message'] : '',
        );
    }

    /**
     * Format milliseconds into a human-readable duration string.
     *
     * @param float $milliseconds
     * @return string
     */
    public function formatTimeDuration($milliseconds)
    {
        if ($milliseconds < 1000) {
            return sprintf('%d ms', $milliseconds);
        }

        $totalSeconds = $milliseconds / 1000;

        if ($totalSeconds < 60) {
            return sprintf('%.1f s', $totalSeconds);
        }

        $minutes = floor($totalSeconds / 60);
        $secondsRemainder = $totalSeconds - ($minutes * 60);

        return sprintf('%d min %.3f s', $minutes, $secondsRemainder);
    }
}
