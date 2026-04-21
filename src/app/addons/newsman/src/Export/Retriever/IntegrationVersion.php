<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Export\AbstractRetriever;

class IntegrationVersion extends AbstractRetriever
{
    public function process($data = array())
    {
        return array('version' => Config::ADDON_VERSION);
    }
}
