<?php

namespace Tygh\Addons\Newsman;

use Tygh\Registry;
use Tygh\Settings;

class Config
{
    const API_URL = 'https://ssl.newsman.app/api/';
    const API_VERSION = '1.2';
    const ADDON_VERSION = '2.0.1';
    const PLATFORM_NAME = 'CS-Cart';

    const OAUTH_AUTHORIZE_URL = 'https://newsman.app/admin/oauth/authorize';
    const OAUTH_TOKEN_URL = 'https://newsman.app/admin/oauth/token';
    const OAUTH_CLIENT_ID = 'nzmplugin';

    const LOG_NONE    = 1;
    const LOG_DEBUG   = 100;
    const LOG_INFO    = 200;
    const LOG_NOTICE  = 250;
    const LOG_WARNING = 300;
    const LOG_ERROR   = 400;

    const DEFAULT_API_TIMEOUT = 30;
    const DEFAULT_LOG_CLEAN_DAYS = 30;

    const SECTION_NAME = 'newsman';

    /** @var array<int,int> storefront_id => company_id, memoized per request */
    protected $storefrontCompanyCache = array();

    /**
     * Read a setting value. When $storefrontId is null, reads from Registry
     * (scoped to the current runtime storefront). When set, reads directly
     * from storage for the requested storefront, resolving the storefront's
     * own company_id first — otherwise Settings::getValue would fall back to
     * the runtime company_id and mask per-storefront overrides set on a
     * different company.
     *
     * @param string   $key
     * @param int|null $storefrontId
     * @return mixed
     */
    public function readValue($key, $storefrontId = null)
    {
        if ($storefrontId === null) {
            return Registry::get('addons.' . self::SECTION_NAME . '.' . $key);
        }

        $storefrontId = (int) $storefrontId;
        if (!array_key_exists($storefrontId, $this->storefrontCompanyCache)) {
            $this->storefrontCompanyCache[$storefrontId] = (int) db_get_field(
                'SELECT company_id FROM ?:storefronts_companies WHERE storefront_id = ?i LIMIT 1',
                $storefrontId
            );
        }
        $companyId = $this->storefrontCompanyCache[$storefrontId];

        // Must create a Settings instance explicitly scoped to this storefront's
        // company_id — passing company_id only to getValue() is ignored when the
        // singleton's runtime company_id differs (Settings::getCompanyId falls
        // back to $this->company_id). See Settings::getCompanyId().
        $value = Settings::instance(array(
            'area'          => 'A',
            'company_id'    => $companyId,
            'storefront_id' => $storefrontId,
        ))->getValue($key, self::SECTION_NAME, $companyId, $storefrontId);

        return $value === false ? null : $value;
    }

    /**
     * @param int|null $storefrontId
     * @return string
     */
    public function getApiKey($storefrontId = null)
    {
        return (string) $this->readValue('api_key', $storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return string
     */
    public function getUserId($storefrontId = null)
    {
        return (string) $this->readValue('user_id', $storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return string
     */
    public function getListId($storefrontId = null)
    {
        return (string) $this->readValue('list_id', $storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return string
     */
    public function getSegmentId($storefrontId = null)
    {
        return (string) $this->readValue('segment_id', $storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return string
     */
    public function getAuthenticateToken($storefrontId = null)
    {
        return (string) $this->readValue('authenticate_token', $storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return string
     */
    public function getCscartMailingListId($storefrontId = null)
    {
        return (string) $this->readValue('cscart_mailing_list_id', $storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return bool
     */
    public function isDoubleOptin($storefrontId = null)
    {
        return $this->readValue('double_optin', $storefrontId) === 'Y';
    }

    /**
     * @param int|null $storefrontId
     * @return bool
     */
    public function isSendUserIp($storefrontId = null)
    {
        return $this->readValue('send_user_ip', $storefrontId) === 'Y';
    }

    /**
     * @param int|null $storefrontId
     * @return string
     */
    public function getServerIp($storefrontId = null)
    {
        return (string) $this->readValue('server_ip', $storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return string
     */
    public function getExportAuthHeaderName($storefrontId = null)
    {
        return (string) $this->readValue('export_auth_header_name', $storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return string
     */
    public function getExportAuthHeaderKey($storefrontId = null)
    {
        return (string) $this->readValue('export_auth_header_key', $storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return bool
     */
    public function isRemarketingActive($storefrontId = null)
    {
        if (!$this->isEnabled($storefrontId)) {
            return false;
        }

        return $this->readValue('remarketing_status', $storefrontId) === 'Y'
            && !empty($this->getRemarketingId($storefrontId));
    }

    /**
     * @param int|null $storefrontId
     * @return string
     */
    public function getRemarketingId($storefrontId = null)
    {
        return (string) $this->readValue('remarketing_id', $storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return string
     */
    public function getRemarketingScriptJs($storefrontId = null)
    {
        return (string) $this->readValue('remarketing_script_js', $storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return bool
     */
    public function isRemarketingAnonymizeIp($storefrontId = null)
    {
        return $this->readValue('remarketing_anonymize_ip', $storefrontId) === 'Y';
    }

    /**
     * @param int|null $storefrontId
     * @return bool
     */
    public function isRemarketingSendTelephone($storefrontId = null)
    {
        return $this->readValue('remarketing_send_telephone', $storefrontId) === 'Y';
    }

    /**
     * @param int|null $storefrontId
     * @return bool
     */
    public function isThemeCartCompatibility($storefrontId = null)
    {
        $value = $this->readValue('theme_cart_compatibility', $storefrontId);

        return $value === null || $value === 'Y';
    }

    /**
     * @param int|null $storefrontId
     * @return int
     */
    public function getLogSeverity($storefrontId = null)
    {
        $value = $this->readValue('log_severity', $storefrontId);

        return $value !== null ? (int) $value : self::LOG_NONE;
    }

    /**
     * @param int|null $storefrontId
     * @return int
     */
    public function getLogCleanDays($storefrontId = null)
    {
        $value = $this->readValue('log_clean_days', $storefrontId);

        return $value !== null && (int) $value > 0 ? (int) $value : self::DEFAULT_LOG_CLEAN_DAYS;
    }

    /**
     * @param int|null $storefrontId
     * @return int
     */
    public function getApiTimeout($storefrontId = null)
    {
        $value = (int) $this->readValue('api_timeout', $storefrontId);

        return $value >= 5 ? $value : self::DEFAULT_API_TIMEOUT;
    }

    /**
     * @param int|null $storefrontId
     * @return bool
     */
    public function isDevActiveUserIp($storefrontId = null)
    {
        return $this->readValue('dev_active_user_ip', $storefrontId) === 'Y';
    }

    /**
     * @param int|null $storefrontId
     * @return string
     */
    public function getDevUserIp($storefrontId = null)
    {
        return (string) $this->readValue('dev_user_ip', $storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return bool
     */
    public function hasApiAccess($storefrontId = null)
    {
        return !empty($this->getUserId($storefrontId)) && !empty($this->getApiKey($storefrontId));
    }

    /**
     * Checks whether the addon is globally active in cscart_addons.
     * CS-Cart addon on/off is a single global status (not per-storefront).
     *
     * @return bool
     */
    public function isActive()
    {
        return Registry::get('addons.' . self::SECTION_NAME . '.status') === 'A';
    }

    /**
     * Whether CS-Cart's Varnish-backed full_page_cache addon is currently
     * active. When true, per-user markup must not be baked into the HTML;
     * use the deferred JS fetch path instead.
     *
     * @return bool
     */
    public function isFullPageCacheActive()
    {
        return Registry::get('addons.full_page_cache.status') === 'A';
    }

    /**
     * @param int|null $storefrontId
     * @return bool
     */
    public function isEnabled($storefrontId = null)
    {
        return $this->isActive()
            && $this->hasApiAccess($storefrontId)
            && !empty($this->getListId($storefrontId));
    }

    /**
     * @param int|null $storefrontId
     * @return bool
     */
    public function isEnabledWithApiOnly($storefrontId = null)
    {
        return $this->isActive() && $this->hasApiAccess($storefrontId);
    }

    /**
     * @param int|null $storefrontId
     * @return bool
     */
    public function isConfigured($storefrontId = null)
    {
        return $this->hasApiAccess($storefrontId);
    }

    /**
     * @param string   $name
     * @param string   $value
     * @param int|null $storefrontId When null, writes to the current runtime storefront
     *                               and refreshes Registry. When set, writes to the given
     *                               storefront and refreshes Registry only if it matches
     *                               the current runtime context.
     */
    public function saveSetting($name, $value, $storefrontId = null)
    {
        Settings::instance()->updateValue(
            $name,
            $value,
            self::SECTION_NAME,
            false,
            null,
            true,
            $storefrontId
        );

        if ($storefrontId === null || (int) $storefrontId === (int) $this->getCurrentStorefrontId()) {
            Registry::set('addons.' . self::SECTION_NAME . '.' . $name, $value);
        }
    }

    /**
     * @param int|null $storefrontId
     * @return array
     */
    public function getAllSettings($storefrontId = null)
    {
        return array(
            'api_key'                    => $this->getApiKey($storefrontId),
            'user_id'                    => $this->getUserId($storefrontId),
            'list_id'                    => $this->getListId($storefrontId),
            'cscart_mailing_list_id'     => $this->getCscartMailingListId($storefrontId),
            'segment_id'                 => $this->getSegmentId($storefrontId),
            'authenticate_token'         => $this->getAuthenticateToken($storefrontId),
            'double_optin'               => $this->isDoubleOptin($storefrontId) ? 'Y' : 'N',
            'send_user_ip'               => $this->isSendUserIp($storefrontId) ? 'Y' : 'N',
            'server_ip'                  => $this->getServerIp($storefrontId),
            'export_auth_header_name'    => $this->getExportAuthHeaderName($storefrontId),
            'export_auth_header_key'     => $this->getExportAuthHeaderKey($storefrontId),
            'remarketing_status'         => $this->isRemarketingActive($storefrontId) ? 'Y' : 'N',
            'remarketing_id'             => $this->getRemarketingId($storefrontId),
            'remarketing_script_js'      => $this->getRemarketingScriptJs($storefrontId),
            'remarketing_anonymize_ip'   => $this->isRemarketingAnonymizeIp($storefrontId) ? 'Y' : 'N',
            'remarketing_send_telephone' => $this->isRemarketingSendTelephone($storefrontId) ? 'Y' : 'N',
            'theme_cart_compatibility'   => $this->isThemeCartCompatibility($storefrontId) ? 'Y' : 'N',
            'log_severity'               => $this->getLogSeverity($storefrontId),
            'log_clean_days'             => $this->getLogCleanDays($storefrontId),
            'api_timeout'                => $this->getApiTimeout($storefrontId),
            'dev_active_user_ip'         => $this->isDevActiveUserIp($storefrontId) ? 'Y' : 'N',
            'dev_user_ip'                => $this->getDevUserIp($storefrontId),
        );
    }

    /**
     * Returns the current runtime storefront ID, or null outside a storefront context.
     *
     * @return int|null
     */
    public function getCurrentStorefrontId()
    {
        if (!empty(\Tygh::$app['storefront'])) {
            $storefront = \Tygh::$app['storefront'];
            if (isset($storefront->storefront_id)) {
                return (int) $storefront->storefront_id;
            }
        }

        return null;
    }

    /**
     * Return all storefront IDs (active and inactive) configured in the store.
     *
     * @return array<int>
     */
    public function getAllStorefrontIds()
    {
        $ids = array();
        if (!empty(\Tygh::$app['storefront.repository'])) {
            $repository = \Tygh::$app['storefront.repository'];
            list($storefronts) = $repository->find();
            foreach ($storefronts as $storefront) {
                $ids[] = (int) $storefront->storefront_id;
            }

            return $ids;
        }

        $rows = db_get_array('SELECT storefront_id FROM ?:storefronts');
        foreach ($rows as $row) {
            $ids[] = (int) $row['storefront_id'];
        }

        return $ids;
    }

    /**
     * @return bool
     */
    public function isEnabledInAny()
    {
        if (!$this->isActive()) {
            return false;
        }

        foreach ($this->getAllStorefrontIds() as $storefrontId) {
            if ($this->isEnabled($storefrontId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all storefront IDs that share the same Newsman list ID and are enabled.
     *
     * @param string $listId
     * @return array<int>
     */
    public function getStorefrontIdsByListId($listId)
    {
        if ($listId === '' || $listId === null) {
            return array();
        }

        $result = array();
        foreach ($this->getAllStorefrontIds() as $storefrontId) {
            if ($this->getListId($storefrontId) === (string) $listId && $this->isEnabled($storefrontId)) {
                $result[] = $storefrontId;
            }
        }

        return $result;
    }

    /**
     * Get all unique list IDs across all enabled storefronts.
     *
     * @return array<string>
     */
    public function getAllListIds()
    {
        $listIds = array();
        foreach ($this->getAllStorefrontIds() as $storefrontId) {
            if (!$this->isEnabled($storefrontId)) {
                continue;
            }
            $listId = $this->getListId($storefrontId);
            if ($listId !== '' && !in_array($listId, $listIds, true)) {
                $listIds[] = $listId;
            }
        }

        return $listIds;
    }

    /**
     * Collect distinct CS-Cart mailing list IDs across all enabled storefronts
     * that share the given Newsman list_id.
     *
     * @param string $newsmanListId
     * @return array<int>
     */
    public function getCscartMailingListIdsByNewsmanListId($newsmanListId)
    {
        $result = array();
        foreach ($this->getStorefrontIdsByListId($newsmanListId) as $storefrontId) {
            $cscartListId = (int) $this->getCscartMailingListId($storefrontId);
            if ($cscartListId > 0 && !in_array($cscartListId, $result, true)) {
                $result[] = $cscartListId;
            }
        }

        return $result;
    }

    /**
     * Collect distinct company IDs across all enabled storefronts that share
     * the given Newsman list_id. Used by company-scoped retrievers
     * (Customers, Orders, Products, Coupons) so a request to one storefront
     * returns/affects data for every storefront sharing the same Newsman list.
     *
     * @param string $newsmanListId
     * @return array<int>
     */
    public function getCompanyIdsByNewsmanListId($newsmanListId)
    {
        $result = array();
        foreach ($this->getStorefrontIdsByListId($newsmanListId) as $storefrontId) {
            $companyId = (int) db_get_field(
                'SELECT company_id FROM ?:storefronts_companies WHERE storefront_id = ?i LIMIT 1',
                $storefrontId
            );
            if (!in_array($companyId, $result, true)) {
                $result[] = $companyId;
            }
        }

        return $result;
    }

    /**
     * @param array<int> $storefrontIds
     * @return bool
     */
    public function isRemarketingSendTelephoneByStorefrontIds(array $storefrontIds)
    {
        foreach ($storefrontIds as $storefrontId) {
            if ($this->isRemarketingSendTelephone((int) $storefrontId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $js
     * @return string
     */
    public static function stripScriptTags($js)
    {
        $js = preg_replace('#^\s*<script[^>]*>\s*#i', '', $js);
        $js = preg_replace('#\s*</script>\s*$#i', '', $js);

        return trim($js);
    }

    /**
     * @param int $length
     * @return string
     */
    public static function generateToken($length = 32)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len = strlen($chars);
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            if (function_exists('random_int')) {
                $token .= $chars[random_int(0, $len - 1)];
            } else {
                $token .= $chars[mt_rand(0, $len - 1)];
            }
        }

        return $token;
    }
}
