<?php

namespace Tygh\Addons\Newsman\Action\Anonymise;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Service\AnonymiseEmail;
use Tygh\Registry;
use Tygh\Settings;

/**
 * Anonymises an email address in the Newsman lists of the given storefronts.
 *
 * A storefront counts when Newsman is connected there: User ID, API key and
 * a Newsman list. The CS-Cart mailing list setting does not matter, because
 * the contact can reach the Newsman list in other ways (orders, imports,
 * Newsman forms). Storefronts that use the same Newsman list (same Newsman
 * user and list) get a single call, and an email address is sent to a
 * Newsman list at most once per request. Each call runs with the
 * storefront's own Newsman settings.
 *
 * "Not on the list" (code 121) is not an error. Any failure is logged and
 * never thrown, so the store action that triggered it always completes.
 */
class Email
{
    /**
     * Registry keys read by the service, the API client and the logger.
     *
     * @var array<string>
     */
    protected static $swappableKeys = array(
        'api_key', 'user_id', 'list_id', 'segment_id', 'authenticate_token',
        'cscart_mailing_list_id', 'double_optin', 'send_user_ip', 'server_ip',
        'api_timeout', 'log_severity', 'log_clean_days',
    );

    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var AnonymiseEmail */
    protected $anonymiseService;

    /** @var array<int,array>|null storefront_id => company_id, settings; per request */
    protected $storefrontConfigs = null;

    /** @var array<string,array<string,bool>> lowercase email => Newsman list key => true */
    protected $sent = array();

    public function __construct(Config $config, Logger $logger, AnonymiseEmail $anonymiseService)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->anonymiseService = $anonymiseService;
    }

    /**
     * @param string     $email
     * @param array<int> $storefrontIds
     * @param string     $reason For the log, e.g. "user delete user_id=5"
     */
    public function execute($email, array $storefrontIds, $reason)
    {
        try {
            $email = trim((string) $email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning(sprintf('Anonymise (%s): invalid email %s, nothing sent to Newsman', $reason, $email));
                return;
            }

            $configs = $this->getStorefrontConfigs();
            $storefrontIds = array_values(array_unique(array_map('intval', $storefrontIds)));
            sort($storefrontIds);

            // One call per Newsman list: the first storefront that uses it.
            $sends = array();
            foreach ($storefrontIds as $storefrontId) {
                if (!isset($configs[$storefrontId])) {
                    continue;
                }
                $listKey = $this->getListKey($configs[$storefrontId]);
                if (!isset($sends[$listKey])) {
                    $sends[$listKey] = $storefrontId;
                }
            }

            if (empty($sends)) {
                $this->logger->debug(sprintf(
                    'Anonymise (%s): Newsman is not connected on storefronts %s, nothing sent for %s',
                    $reason,
                    implode(',', $storefrontIds),
                    $email
                ));
                return;
            }

            $emailKey = strtolower($email);
            foreach ($sends as $listKey => $storefrontId) {
                if (isset($this->sent[$emailKey][$listKey])) {
                    $this->logger->debug(sprintf(
                        'Anonymise (%s): %s was already sent to Newsman list %s in this request',
                        $reason,
                        $email,
                        $configs[$storefrontId]['settings']['list_id']
                    ));
                    continue;
                }
                $this->sent[$emailKey][$listKey] = true;

                $this->withStorefrontScope($storefrontId, $configs[$storefrontId], function () use ($email, $storefrontId, $reason) {
                    $this->send($email, $storefrontId, $reason);
                });
            }
        } catch (\Throwable $e) {
            $this->logger->logException($e);
        }
    }

    /**
     * Storefronts where Newsman is connected.
     *
     * @return array<int>
     */
    public function getConnectedStorefrontIds()
    {
        return array_keys($this->getStorefrontConfigs());
    }

    /**
     * Must run inside withStorefrontScope(), the service reads the Registry.
     *
     * @param string $email
     * @param int    $storefrontId
     * @param string $reason
     */
    protected function send($email, $storefrontId, $reason)
    {
        $listId = $this->config->getListId();

        $this->logger->info(sprintf(
            'Anonymise (%s): storefront=%d anonymise %s in Newsman list %s',
            $reason,
            $storefrontId,
            $email,
            $listId
        ));

        $result = $this->anonymiseService->execute($email);

        if (!is_array($result) || !isset($result['error'])) {
            return;
        }

        if (isset($result['code']) && (int) $result['code'] === AnonymiseEmail::ERROR_NO_SUCH_SUBSCRIBER) {
            $this->logger->info(sprintf(
                'Anonymise (%s): %s is not on Newsman list %s, nothing to anonymise',
                $reason,
                $email,
                $listId
            ));
            return;
        }

        $this->logger->error(sprintf(
            'Anonymise (%s): storefront=%d anonymise failed for %s: %s',
            $reason,
            $storefrontId,
            $email,
            Client::errorMessage($result)
        ));
    }

    /**
     * Storefronts with Newsman connected, read with each storefront's own
     * company_id (the admin runtime company would hide per-storefront values).
     *
     * @return array<int,array> storefront_id => company_id, settings
     */
    protected function getStorefrontConfigs()
    {
        if ($this->storefrontConfigs !== null) {
            return $this->storefrontConfigs;
        }

        $configs = array();
        foreach ($this->config->getAllStorefrontIds() as $storefrontId) {
            $companyId = (int) db_get_field(
                'SELECT company_id FROM ?:storefronts_companies WHERE storefront_id = ?i LIMIT 1',
                $storefrontId
            );
            $settings = Settings::instance(array(
                'area'          => 'A',
                'company_id'    => $companyId,
                'storefront_id' => $storefrontId,
            ))->getValues(Config::SECTION_NAME, Settings::ADDON_SECTION, false, $companyId, $storefrontId);
            $settings = is_array($settings) ? $settings : array();

            if (empty($settings['api_key']) || empty($settings['user_id']) || empty($settings['list_id'])) {
                continue;
            }

            $configs[(int) $storefrontId] = array(
                'company_id' => $companyId,
                'settings'   => $settings,
            );
        }

        $this->storefrontConfigs = $configs;

        return $configs;
    }

    /**
     * @param array $cfg Entry of getStorefrontConfigs()
     * @return string
     */
    protected function getListKey(array $cfg)
    {
        return $cfg['settings']['user_id'] . '|' . $cfg['settings']['list_id'];
    }

    /**
     * Run $fn with the runtime Newsman settings of $storefrontId, then
     * restore them.
     *
     * @param int      $storefrontId
     * @param array    $cfg Entry of getStorefrontConfigs()
     * @param callable $fn
     */
    protected function withStorefrontScope($storefrontId, array $cfg, callable $fn)
    {
        $savedRegistry = array();
        foreach (self::$swappableKeys as $k) {
            $savedRegistry[$k] = Registry::get('addons.newsman.' . $k);
        }
        $savedRuntimeCompanyId = Registry::get('runtime.company_id');
        $savedRuntimeStorefrontId = Registry::get('runtime.storefront_id');

        foreach (self::$swappableKeys as $k) {
            if (array_key_exists($k, $cfg['settings'])) {
                Registry::set('addons.newsman.' . $k, $cfg['settings'][$k]);
            }
        }
        Registry::set('runtime.company_id', $cfg['company_id']);
        Registry::set('runtime.storefront_id', $storefrontId);

        try {
            $fn();
        } catch (\Throwable $e) {
            $this->logger->logException($e);
        } finally {
            foreach (self::$swappableKeys as $k) {
                Registry::set('addons.newsman.' . $k, $savedRegistry[$k]);
            }
            Registry::set('runtime.company_id', $savedRuntimeCompanyId);
            Registry::set('runtime.storefront_id', $savedRuntimeStorefrontId);
        }
    }
}
