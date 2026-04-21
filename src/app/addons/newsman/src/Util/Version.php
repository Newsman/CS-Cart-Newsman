<?php

namespace Tygh\Addons\Newsman\Util;

use Tygh\Registry;

class Version
{
    /**
     * @return string
     */
    public static function getAddonVersion()
    {
        $version = Registry::get('addons.newsman.version');
        if (!empty($version)) {
            return (string) $version;
        }

        $addonXml = Registry::get('config.dir.addons') . 'newsman/addon.xml';
        if (file_exists($addonXml)) {
            $xml = @simplexml_load_file($addonXml);
            if ($xml && isset($xml->version)) {
                return (string) $xml->version;
            }
        }

        return \Tygh\Addons\Newsman\Config::ADDON_VERSION;
    }
}
