<?php

namespace Tygh\Addons\Newsman\Service;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

class GetRemarketingSettings
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
     * @param string|null $userId Override configured user ID (for cross-storefront calls).
     * @param string|null $apiKey Override configured API key.
     * @param string|null $listId Override configured list ID.
     * @return array
     */
    public function execute($userId = null, $apiKey = null, $listId = null)
    {
        $this->logger->info('Try to get remarketing settings');

        $context = $this->client->createContext();
        $context->setEndpoint('remarketing.getSettings');

        if ($userId !== null) {
            $context->setUserId($userId);
        }
        if ($apiKey !== null) {
            $context->setApiKey($apiKey);
        }
        if ($listId !== null) {
            $context->setListId($listId);
        }

        $result = $this->client->get($context, array('list_id' => $context->getListId()));

        $this->logger->info('Get remarketing settings done');

        return $result;
    }
}
