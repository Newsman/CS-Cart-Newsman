<?php

namespace Tygh\Addons\Newsman\Export;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Export\V1\ApiV1Exception;
use Tygh\Addons\Newsman\Logger;

class Processor
{
    /** @var array */
    protected $retrievers;

    /** @var Authenticator */
    protected $authenticator;

    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /**
     * @param array         $retrievers
     * @param Authenticator $authenticator
     * @param Config        $config
     * @param Logger        $logger
     */
    public function __construct($retrievers, Authenticator $authenticator, Config $config, Logger $logger)
    {
        $this->retrievers = $retrievers;
        $this->authenticator = $authenticator;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param string $retrieverCode
     * @param array  $params
     * @param Renderer $renderer
     */
    public function execute($retrieverCode, $params, Renderer $renderer)
    {
        if (!$this->authenticator->authenticate()) {
            $this->logger->warning('API v1: Authentication failed');
            $renderer->sendError('Authentication failed', 1001, 401);
            return;
        }

        if (!isset($this->retrievers[$retrieverCode])) {
            $this->logger->warning(sprintf('API v1: Unknown retriever: %s', $retrieverCode));
            $renderer->sendError('Unknown method', 1004, 400);
            return;
        }

        $retriever = $this->retrievers[$retrieverCode];

        // Log request params (sanitize: exclude API key and auth token)
        $safeParams = $params;
        unset($safeParams['api_key'], $safeParams['newsman_api_key'], $safeParams['nzmhash']);
        $this->logger->info(sprintf(
            'Processor: %s with params %s',
            $retrieverCode,
            json_encode($safeParams)
        ));

        try {
            $result = $retriever->process($params);
            $renderer->sendSuccess($result);
        } catch (ApiV1Exception $e) {
            $this->logger->error(sprintf(
                'API v1 retriever %s error [%d]: %s',
                $retrieverCode,
                $e->getErrorCode(),
                $e->getMessage()
            ));
            $renderer->sendError($e->getMessage(), $e->getErrorCode(), $e->getHttpStatus());
        } catch (\Exception $e) {
            $this->logger->error(sprintf('API v1 retriever %s error: %s', $retrieverCode, $e->getMessage()));
            $renderer->sendError($e->getMessage(), 1003, 500);
        }
    }
}
