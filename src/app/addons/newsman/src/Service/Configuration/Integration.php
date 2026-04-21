<?php

namespace Tygh\Addons\Newsman\Service\Configuration;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Service\GetRemarketingSettings;
use Tygh\Addons\Newsman\Service\SaveListIntegrationSetup;

/**
 * Handles all side effects of a Newsman credentials change:
 *  - ensures an authenticate token exists
 *  - calls integration.saveListIntegrationSetup on Newsman
 *  - fetches remarketing settings and derives the tracking ID + script
 *  - propagates authenticate token and remarketing values to every
 *    storefront that shares the same list ID
 */
class Integration
{
    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var SaveListIntegrationSetup */
    protected $saveIntegration;

    /** @var GetRemarketingSettings */
    protected $getRemarketingSettings;

    public function __construct(
        Config $config,
        Logger $logger,
        SaveListIntegrationSetup $saveIntegration,
        GetRemarketingSettings $getRemarketingSettings
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->saveIntegration = $saveIntegration;
        $this->getRemarketingSettings = $getRemarketingSettings;
    }

    /**
     * Run the full post-save sync for the given storefront.
     * If $storefrontId is null, uses the current runtime storefront.
     *
     * @param string   $storeUrl     Public storefront API URL.
     * @param int|null $storefrontId
     * @return void
     */
    public function syncAndPropagate($storeUrl, $storefrontId = null)
    {
        $userId = $this->config->getUserId($storefrontId);
        $apiKey = $this->config->getApiKey($storefrontId);
        $listId = $this->config->getListId($storefrontId);

        if ($userId === '' || $apiKey === '' || $listId === '') {
            return;
        }

        $authenticateToken = $this->ensureAuthenticateToken($storefrontId);

        try {
            $this->saveIntegration->execute($storeUrl, $authenticateToken, $userId, $apiKey, $listId);
        } catch (\Throwable $e) {
            $this->logger->logException($e);
        }

        $this->fetchAndSaveRemarketingSettings($userId, $apiKey, $listId, $storefrontId);
        $this->propagateToLinkedStorefronts($listId, $storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return string
     */
    public function ensureAuthenticateToken($storefrontId = null)
    {
        $token = $this->config->getAuthenticateToken($storefrontId);
        if ($token !== '') {
            return $token;
        }

        $token = Config::generateToken(32);
        $this->config->saveSetting('authenticate_token', $token, $storefrontId);

        return $token;
    }

    /**
     * @param string   $userId
     * @param string   $apiKey
     * @param string   $listId
     * @param int|null $storefrontId
     * @return void
     */
    public function fetchAndSaveRemarketingSettings($userId, $apiKey, $listId, $storefrontId = null)
    {
        try {
            $result = $this->getRemarketingSettings->execute($userId, $apiKey, $listId);
        } catch (\Throwable $e) {
            $this->logger->logException($e);

            return;
        }

        if (!is_array($result) || isset($result['error'])) {
            return;
        }

        $siteId = isset($result['site_id']) ? (string) $result['site_id'] : '';
        $formId = isset($result['form_id']) ? (string) $result['form_id'] : '';
        $controlListHash = isset($result['control_list_hash']) ? (string) $result['control_list_hash'] : '';

        if ($siteId !== '' && $formId !== '') {
            $trackingId = $siteId . '-' . $listId . '-' . $formId . '-' . $controlListHash;
            $this->config->saveSetting('remarketing_id', $trackingId, $storefrontId);
            $this->config->saveSetting('remarketing_status', 'Y', $storefrontId);
        }

        if (!empty($result['javascript'])) {
            $js = Config::stripScriptTags((string) $result['javascript']);
            $this->config->saveSetting('remarketing_script_js', $js, $storefrontId);
        }
    }

    /**
     * Copies authenticate token + remarketing values from the source
     * storefront onto every other storefront configured with the same
     * Newsman list ID.
     *
     * @param string   $listId
     * @param int|null $sourceStorefrontId
     * @return void
     */
    public function propagateToLinkedStorefronts($listId, $sourceStorefrontId = null)
    {
        try {
            $linkedIds = $this->config->getStorefrontIdsByListId($listId);
            if (count($linkedIds) <= 1) {
                return;
            }

            $authenticateToken = $this->config->getAuthenticateToken($sourceStorefrontId);
            $remarketingId = $this->config->getRemarketingId($sourceStorefrontId);
            $remarketingStatus = $this->config->isRemarketingActive($sourceStorefrontId) ? 'Y' : 'N';
            $remarketingScriptJs = $this->config->getRemarketingScriptJs($sourceStorefrontId);

            $resolvedSource = $sourceStorefrontId === null
                ? $this->config->getCurrentStorefrontId()
                : (int) $sourceStorefrontId;

            foreach ($linkedIds as $linkedId) {
                if ($resolvedSource !== null && $linkedId === $resolvedSource) {
                    continue;
                }
                if ($authenticateToken !== '') {
                    $this->config->saveSetting('authenticate_token', $authenticateToken, $linkedId);
                }
                if ($remarketingId !== '') {
                    $this->config->saveSetting('remarketing_id', $remarketingId, $linkedId);
                    $this->config->saveSetting('remarketing_status', $remarketingStatus, $linkedId);
                }
                if ($remarketingScriptJs !== '') {
                    $this->config->saveSetting('remarketing_script_js', $remarketingScriptJs, $linkedId);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->logException($e);
        }
    }
}
