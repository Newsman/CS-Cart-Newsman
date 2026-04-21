<?php

namespace Tygh\Addons\Newsman\Remarketing;

class CustomerIdentify
{
    /**
     * @param string $email
     * @param string $firstname
     * @param string $lastname
     * @return string
     */
    public function getHtml($email, $firstname = '', $lastname = '')
    {
        if (empty($email)) {
            return '';
        }

        $emailJs = JsHelper::escapeJs($email);
        $firstJs = JsHelper::escapeJs($firstname);
        $lastJs = JsHelper::escapeJs($lastname);

        $js = "_nzm.run('identify', {"
            . "'email': '{$emailJs}'"
            . ", 'first_name': '{$firstJs}'"
            . ", 'last_name': '{$lastJs}'"
            . "});\n";

        return JsHelper::wrapScript($js);
    }
}
