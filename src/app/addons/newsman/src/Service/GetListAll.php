<?php

namespace Tygh\Addons\Newsman\Service;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

class GetListAll
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
     * @return array
     */
    public function execute()
    {
        $this->logger->info('Try to get all lists');

        $context = $this->client->createContext();
        $context->setEndpoint('list.all');

        $result = $this->client->get($context);

        $this->logger->info('Get all lists done');

        return $result;
    }
}
