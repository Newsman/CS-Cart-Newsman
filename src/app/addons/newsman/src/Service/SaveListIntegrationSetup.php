<?php

namespace Tygh\Addons\Newsman\Service;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Util\ServerIpResolver;

class SaveListIntegrationSetup
{
    /** @var Client */
    protected $client;

    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var ServerIpResolver */
    protected $serverIpResolver;

    public function __construct(
        Client $client,
        Config $config,
        Logger $logger,
        ServerIpResolver $serverIpResolver
    ) {
        $this->client = $client;
        $this->config = $config;
        $this->logger = $logger;
        $this->serverIpResolver = $serverIpResolver;
    }

    /**
     * Register the integration on Newsman's side.
     *
     * @param string      $storeUrl          Public callback URL for the storefront.
     * @param string      $authenticateToken Token Newsman will present back to us.
     * @param string|null $userId            When set, override the configured user ID (cross-storefront).
     * @param string|null $apiKey            When set, override the configured API key.
     * @param string|null $listId            When set, override the configured list ID.
     * @return array
     */
    public function execute(
        $storeUrl,
        $authenticateToken,
        $userId = null,
        $apiKey = null,
        $listId = null
    ) {
        $this->logger->info(sprintf('Try to save list integration setup for %s', $storeUrl));

        $context = $this->client->createContext();
        $context->setEndpoint('integration.saveListIntegrationSetup');

        if ($userId !== null) {
            $context->setUserId($userId);
        }
        if ($apiKey !== null) {
            $context->setApiKey($apiKey);
        }
        if ($listId !== null) {
            $context->setListId($listId);
        }

        $postParams = array(
            'list_id'     => $context->getListId(),
            'integration' => 'cscart',
            'payload'     => array(
                'api_url'                   => $storeUrl,
                'api_key'                   => $authenticateToken,
                'plugin_version'            => Config::ADDON_VERSION,
                'platform_version'          => defined('PRODUCT_VERSION') ? PRODUCT_VERSION : '',
                'platform_language'         => 'PHP',
                'platform_language_version' => phpversion(),
                'platform_server_ip'        => $this->serverIpResolver->resolve(),
            ),
        );

        $result = $this->client->post($context, array(), $postParams);

        $this->logger->info(sprintf('Save list integration setup for %s done', $storeUrl));

        return $result;
    }
}
