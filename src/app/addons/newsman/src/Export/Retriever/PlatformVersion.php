<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Export\AbstractRetriever;

class PlatformVersion extends AbstractRetriever
{
    public function process($data = array())
    {
        return array('version' => defined('PRODUCT_VERSION') ? PRODUCT_VERSION : 'unknown');
    }
}
