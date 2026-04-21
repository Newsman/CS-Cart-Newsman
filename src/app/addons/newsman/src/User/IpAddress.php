<?php

namespace Tygh\Addons\Newsman\User;

use Tygh\Addons\Newsman\Config;

class IpAddress
{
    /** @var Config */
    protected $config;

    /** @var HostIpAddress */
    protected $hostIpAddress;

    /** @var RemoteAddress */
    protected $remoteAddress;

    /**
     * @param Config $config
     * @param HostIpAddress $hostIpAddress
     * @param RemoteAddress $remoteAddress
     */
    public function __construct(Config $config, HostIpAddress $hostIpAddress, RemoteAddress $remoteAddress)
    {
        $this->config = $config;
        $this->hostIpAddress = $hostIpAddress;
        $this->remoteAddress = $remoteAddress;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        if ($this->config->isDevActiveUserIp()) {
            $devIp = $this->config->getDevUserIp();
            if (!empty($devIp)) {
                return $devIp;
            }
        }

        if (!$this->config->isSendUserIp()) {
            return $this->hostIpAddress->getIp();
        }

        $ip = $this->remoteAddress->getRemoteAddress();

        if ('127.0.0.1' === $ip || empty($ip)) {
            return $this->hostIpAddress->getIp();
        }

        return $ip;
    }
}
