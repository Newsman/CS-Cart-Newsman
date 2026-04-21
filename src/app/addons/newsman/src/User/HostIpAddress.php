<?php

namespace Tygh\Addons\Newsman\User;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Util\ServerIpResolver;

class HostIpAddress
{
    const NOT_FOUND = 'not found';

    /** @var Config */
    protected $config;

    /** @var ServerIpResolver */
    protected $serverIpResolver;

    /** @var string|null */
    protected $ip;

    /**
     * @param Config $config
     * @param ServerIpResolver $serverIpResolver
     */
    public function __construct(Config $config, ServerIpResolver $serverIpResolver)
    {
        $this->config = $config;
        $this->serverIpResolver = $serverIpResolver;
        $this->ip = null;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        if (null !== $this->ip) {
            return $this->ip;
        }

        $ip = $this->config->getServerIp();
        if (!empty($ip)) {
            if (self::NOT_FOUND === $ip) {
                $this->ip = '';
            } else {
                $this->ip = $ip;
            }

            return $this->ip;
        }

        $ip = $this->serverIpResolver->resolve();
        if (!empty($ip)) {
            $this->config->saveSetting('server_ip', $ip);
            $this->ip = $ip;

            return $this->ip;
        }

        $this->config->saveSetting('server_ip', self::NOT_FOUND);
        $this->ip = '';

        return $this->ip;
    }
}
