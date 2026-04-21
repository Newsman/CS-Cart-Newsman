<?php

namespace Tygh\Addons\Newsman\Api;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

class Client
{
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $elapsedMs = round((microtime(true) - $startTime) * 1000);
        $this->logger->debug(sprintf('[%s] Requested in %s', $logHash, $this->formatTimeDuration($elapsedMs)));

        if ($curlError) {
            $this->logger->error(sprintf('[%s] cURL error: %s', $logHash, $curlError));
            return array('error' => $curlError);
        }

        $this->logger->debug(sprintf('[%s] Raw response (HTTP %d): %s', $logHash, $httpCode, substr($response, 0, 500)));

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $this->logger->error(sprintf('[%s] %d | %s', $logHash, $httpCode, substr($response, 0, 500)));
            $errorMsg = isset($decoded['err']) ? $decoded['err'] : "HTTP $httpCode";
            return array('error' => $errorMsg);
        }

        if ($decoded === null) {
            $this->logger->error(sprintf('[%s] Invalid JSON: %s', $logHash, substr($response, 0, 200)));
            return array('error' => 'Invalid JSON response');
        }

        $apiError = $this->parseApiError($decoded);
        if ($apiError !== false) {
            $this->logger->warning(sprintf('[%s] %d | %s', $logHash, $apiError['code'], $apiError['message']));
            return array('error' => $apiError['message']);
        }

        $this->logger->notice(sprintf('[%s] %s', $logHash, json_encode($decoded)));

        return $decoded;
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
