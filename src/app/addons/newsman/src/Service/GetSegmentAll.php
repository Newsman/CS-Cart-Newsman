<?php

namespace Tygh\Addons\Newsman\Service;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

class GetSegmentAll
{
    /** @var Client */
    protected $client;

    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    public function __construct(Client $client, Config $config, Logger $logger)
    {
        $this->client = $client;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param string $listId
     * @return array
     */
    public function execute($listId = '')
    {
        $resolvedListId = $listId;

        $context = $this->client->createContext();
        $context->setEndpoint('segment.all');

        $params = array();
        if (!empty($listId)) {
            $params['list_id'] = $listId;
        } elseif (!empty($context->getListId())) {
            $resolvedListId = $context->getListId();
            $params['list_id'] = $resolvedListId;
        }

        $this->logger->info(sprintf('Try to get all segments for list %s', $resolvedListId));

        $result = $this->client->get($context, $params);

        $this->logger->info(sprintf('Get all segments for list %s done', $resolvedListId));

        return $result;
    }
}
