<?php

namespace Tygh\Addons\Newsman\User;

class RemoteAddress
{
    /**
     * @var array
     */
    protected $alternativeHeaders;

    /**
     * @var string|null
     */
    protected $remoteAddress;

    /**
     * @param array $alternativeHeaders
     */
    public function __construct(array $alternativeHeaders = array())
    {
        $this->alternativeHeaders = $alternativeHeaders;
        $this->remoteAddress = null;
    }

    /**
     * @return string|null
     */
    public function readAddress()
    {
        $remoteAddress = null;
        foreach ($this->alternativeHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $remoteAddress = (string) $_SERVER[$header];
                break;
            }
        }

        if (null === $remoteAddress) {
            $remoteAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
        }

        return $remoteAddress;
    }

    /**
     * @param string $remoteAddress
     * @return string|null
     */
    public function filterAddress($remoteAddress)
    {
        if (strpos($remoteAddress, ',') !== false) {
            $ipList = explode(',', $remoteAddress);
        } else {
            $ipList = array($remoteAddress);
        }

        $ipList = array_filter(
            $ipList,
            function ($ip) {
                return (bool) filter_var(
                    trim($ip),
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                );
            }
        );

        reset($ipList);
        $remoteAddress = empty($ipList) ? '' : trim(end($ipList));

        return $remoteAddress !== '' ? $remoteAddress : null;
    }

    /**
     * @return string
     */
    public function getRemoteAddress()
    {
        if (null !== $this->remoteAddress) {
            return $this->remoteAddress;
        }

        $rawAddress = $this->readAddress();
        if (null === $rawAddress) {
            $this->remoteAddress = '';

            return '';
        }

        $filtered = $this->filterAddress($rawAddress);
        $this->remoteAddress = $filtered !== null ? $filtered : '';

        return $this->remoteAddress;
    }
}
