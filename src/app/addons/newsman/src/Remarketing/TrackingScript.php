<?php

namespace Tygh\Addons\Newsman\Remarketing;

use Tygh\Addons\Newsman\Config;

class TrackingScript
{
    /** @var Config */
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $currencyCode
     * @return string
     */
    public function getHtml($currencyCode = '')
    {
        $scriptJs = $this->config->getRemarketingScriptJs();

        if (empty($scriptJs)) {
            return '';
        }

        $html = '<script type="text/javascript" data-no-defer>' . "\n";
        $html .= 'var _nzm = _nzm || []; var _nzm_config = _nzm_config || [];' . "\n";
        $html .= '_nzm_config["disable_datalayer"] = 1;' . "\n";

        if ($this->config->isRemarketingAnonymizeIp()) {
            $html .= '_nzm_config["anonymizeIp"] = true;' . "\n";
        }

        $html .= '</script>' . "\n";
        $html .= '<script type="text/javascript" data-no-defer>' . "\n";
        $html .= $scriptJs . "\n";
        $html .= '</script>' . "\n";

        if (!empty($currencyCode)) {
            $html .= '<script type="text/javascript" data-no-defer>' . "\n";
            $html .= "_nzm.run('set', 'currencyCode', '" . JsHelper::escapeJs($currencyCode) . "');\n";
            $html .= '</script>' . "\n";
        }

        return $html;
    }
}
