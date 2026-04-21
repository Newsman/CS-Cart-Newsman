<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Export\AbstractRetriever;

class IntegrationName extends AbstractRetriever
{
    public function process($data = array())
    {
        return array('name' => 'newsman');
    }
}
