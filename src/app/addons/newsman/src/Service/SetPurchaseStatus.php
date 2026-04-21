<?php

namespace Tygh\Addons\Newsman\Service;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

class SetPurchaseStatus
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
     * @param string $orderId
     * @param string $status
     * @return array
     */
    public function execute($orderId, $status)
    {
        $this->logger->info(sprintf('Try to set purchase status for order %s to %s', $orderId, $status));

        $context = $this->client->createContext();
        $context->setEndpoint('remarketing.setPurchaseStatus');

        $getParams = array(
            'list_id'  => $context->getListId(),
            'order_id' => (string) $orderId,
            'status'   => $status,
        );

        $result = $this->client->get($context, $getParams);

        $this->logger->info(sprintf('Set purchase status for order %s to %s done', $orderId, $status));

        return $result;
    }
}
