<?php

namespace Tygh\Addons\Newsman\Util;

class ServerIpResolver
{
    /**
     * @var array
     */
    protected $services = array(
        'https://api.ipify.org',
        'https://ipinfo.io/ip',
        'https://ifconfig.me/ip',
        'https://icanhazip.com',
    );

    /**
     * @return string
     */
    public function resolve()
    {
        $services = $this->services;
        shuffle($services);

        foreach ($services as $url) {
            $ip = $this->fetchFromService($url);
            if ($this->isValidIp($ip)) {
                return $ip;
            }
        }

        if (!empty($_SERVER['SERVER_ADDR'])) {
            return (string) $_SERVER['SERVER_ADDR'];
        }

        $hostname = gethostname();
        if (false !== $hostname) {
            $ip = gethostbyname($hostname);
            if ($ip !== $hostname) {
                return $ip;
            }
        }

        return '';
    }

    /**
     * @param string $url
     * @return string
     */
    public function fetchFromService($url)
    {
        if (!function_exists('curl_init')) {
            return '';
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (false === $result || 200 !== $httpCode) {
            return '';
        }

        return trim((string) $result);
    }

    /**
     * @param string $ip
     * @return bool
     */
    public function isValidIp($ip)
    {
        return !empty($ip) && false !== filter_var($ip, FILTER_VALIDATE_IP);
    }
}
