<?php

namespace Tygh\Addons\Newsman\Remarketing;

class JsHelper
{
    /**
     * @param string $str
     * @return string
     */
    public static function escapeJs($str)
    {
        return addcslashes(htmlspecialchars($str, ENT_QUOTES, 'UTF-8'), "\\\n\r\t\"'");
    }

    /**
     * @param string $js
     * @return string
     */
    public static function wrapScript($js)
    {
        if (empty($js)) {
            return '';
        }

        return '<script type="text/javascript" data-no-defer>' . "\n" . $js . "\n" . '</script>' . "\n";
    }
}
