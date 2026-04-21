<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Export\AbstractRetriever;
use Tygh\Addons\Newsman\Export\V1\ApiV1Exception;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Service\Configuration\Integration;

class RefreshRemarketing extends AbstractRetriever
{
    /** @var Client */
    protected $client;

    /** @var Integration */
    protected $integration;

    public function __construct(Config $config, Logger $logger, Client $client, Integration $integration)
    {
        parent::__construct($config, $logger);
        $this->client = $client;
        $this->integration = $integration;
    }

    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        $refresh = isset($data['refresh']) ? $data['refresh'] : '';
        if ($refresh !== '1' && $refresh !== 1) {
            throw new ApiV1Exception(9001, 'Parameter refresh=1 is required', 400);
        }

        if (!$this->config->isConfigured()) {
            throw new ApiV1Exception(9002, 'Plugin is not configured', 400);
        }

        $listId = $this->config->getListId();
        if (empty($listId)) {
            throw new ApiV1Exception(9003, 'No list ID configured', 400);
        }

        $context = $this->client->createContext();
        $context->setEndpoint('remarketing.getSettings');

        $result = $this->client->get($context, array('list_id' => $listId));

        if (isset($result['error'])) {
            throw new ApiV1Exception(9004, 'Failed to fetch remarketing settings: ' . $result['error'], 500);
        }

        $oldJs = $this->config->getRemarketingScriptJs();
        $newJs = '';
        $remarketingId = '';

        if (!empty($result['javascript'])) {
            $newJs = Config::stripScriptTags($result['javascript']);
            $this->config->saveSetting('remarketing_script_js', $newJs);
        }

        $siteId = isset($result['site_id']) ? $result['site_id'] : '';
        $formId = isset($result['form_id']) ? $result['form_id'] : '';
        $controlListHash = isset($result['control_list_hash']) ? $result['control_list_hash'] : '';

        if (!empty($siteId) && !empty($formId)) {
            $remarketingId = $siteId . '-' . $listId . '-' . $formId . '-' . $controlListHash;
            $this->config->saveSetting('remarketing_id', $remarketingId);
            $this->config->saveSetting('remarketing_status', 'Y');
        }

        $this->integration->propagateToLinkedStorefronts($listId);

        $this->logger->info('Remarketing settings refreshed');

        return array(
            'status'              => 1,
            'old_remarketing_js'  => $oldJs,
            'new_remarketing_js'  => $newJs,
        );
    }
}
