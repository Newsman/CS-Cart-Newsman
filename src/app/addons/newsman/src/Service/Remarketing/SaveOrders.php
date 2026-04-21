<?php

namespace Tygh\Addons\Newsman\Service\Remarketing;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

class SaveOrders
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
     * @param array  $orders
     * @return array
     */
    public function execute($listId, $orders)
    {
        $context = $this->client->createContext();
        $context->setEndpoint('remarketing.saveOrders');

        $this->logger->info(sprintf('Sending %d orders via remarketing.saveOrders', count($orders)));

        $postParams = array(
            $listId,
            $orders,
        );

        $result = $this->client->post($context, array(), $postParams);

        if (isset($result['error'])) {
            throw new \RuntimeException('remarketing.saveOrders API error: ' . $result['error']);
        }

        $this->logger->info('Batch order export completed successfully');

        return $result;
    }
}
