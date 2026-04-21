<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Export\AbstractRetriever;

class ServerCloudflare extends AbstractRetriever
{
    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        return array('cloudflare' => !empty($_SERVER['HTTP_CF_RAY']));
    }
}
