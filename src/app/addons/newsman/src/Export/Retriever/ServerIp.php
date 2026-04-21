<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Export\AbstractRetriever;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Util\ServerIpResolver;

class ServerIp extends AbstractRetriever
{
    /** @var ServerIpResolver */
    protected $serverIpResolver;

    public function __construct(Config $config, Logger $logger, ServerIpResolver $serverIpResolver)
    {
        parent::__construct($config, $logger);
        $this->serverIpResolver = $serverIpResolver;
    }

    public function process($data = array())
    {
        return array('ip' => $this->serverIpResolver->resolve());
    }
}
