<?php

namespace Tygh\Addons\Newsman\Export;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Export\V1\ApiV1Exception;
use Tygh\Addons\Newsman\Logger;

class Router
{
    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var PayloadParser */
    protected $parser;

    /** @var Processor */
    protected $processor;

    /** @var Renderer */
    protected $renderer;

    public function __construct(
        Config $config,
        Logger $logger,
        PayloadParser $parser,
        Processor $processor,
        Renderer $renderer
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->parser = $parser;
        $this->processor = $processor;
        $this->renderer = $renderer;
    }

    public function execute()
    {
        $rawBody = file_get_contents('php://input');

        // API v1: JSON POST
        if ($this->parser->isJsonPayload($rawBody)) {
            try {
                $parsed = $this->parser->parse($rawBody);
            } catch (ApiV1Exception $e) {
                $this->logger->warning(sprintf('API v1 parse [%d]: %s', $e->getErrorCode(), $e->getMessage()));
                $this->renderer->sendError($e->getMessage(), $e->getErrorCode(), $e->getHttpStatus());
                return;
            }

            $this->logger->info(sprintf('API v1: %s', $parsed['method']));
            $this->processor->execute($parsed['retriever_code'], $parsed['params'], $this->renderer);
            return;
        }

        // Legacy query-string access
        $retrieverCode = isset($_REQUEST['newsman']) ? $_REQUEST['newsman'] : '';
        if (empty($retrieverCode)) {
            $this->renderer->sendError('Missing method or newsman parameter', 1000, 400);
            return;
        }

        // Block legacy access for endpoints available in API v1
        if (in_array($retrieverCode, PayloadParser::$methodMap, true)) {
            $this->renderer->sendError('This endpoint is only available via API v1 (JSON POST).', 1005, 400);
            return;
        }

        $params = $_REQUEST;
        unset($params['dispatch'], $params['newsman'], $params['nzmhash']);

        $this->logger->info(sprintf('API legacy: %s', $retrieverCode));
        $this->processor->execute($retrieverCode, $params, $this->renderer);
    }
}
