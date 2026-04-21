<?php

namespace Tygh\Addons\Newsman\Remarketing;

class PageView
{
    /**
     * @return string
     */
    public function getHtml()
    {
        return JsHelper::wrapScript("_nzm.run('send', 'pageview');\n");
    }
}
