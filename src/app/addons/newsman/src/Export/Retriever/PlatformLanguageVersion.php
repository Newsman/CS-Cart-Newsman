<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Export\AbstractRetriever;

class PlatformLanguageVersion extends AbstractRetriever
{
    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        return array('language_version' => phpversion());
    }
}
