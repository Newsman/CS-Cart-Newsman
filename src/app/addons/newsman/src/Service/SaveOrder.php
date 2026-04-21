<?php

namespace Tygh\Addons\Newsman\Service;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

class SaveOrder
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
     * @param array $orderDetails
     * @param array $orderProducts
     * @return array
     */
    public function execute($orderDetails, $orderProducts = array())
    {
        $orderId = isset($orderDetails['order_no']) ? $orderDetails['order_no'] : 'unknown';
        $this->logger->info(sprintf('Try to save order %s', $orderId));

        $context = $this->client->createContext();
        $context->setEndpoint('remarketing.saveOrder');

        $postParams = array(
            'list_id'        => $context->getListId(),
            'order_details'  => $orderDetails,
            'order_products' => $orderProducts,
        );

        $result = $this->client->post($context, array(), $postParams);

        $this->logger->info(sprintf('Saved order %s', $orderId));

        return $result;
    }
}
