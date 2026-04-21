<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Export\AbstractRetriever;

class PlatformLanguage extends AbstractRetriever
{
    public function process($data = array())
    {
        return array('language' => 'PHP');
    }
}
