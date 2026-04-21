<?php

namespace Tygh\Addons\Newsman\Service\Sms;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;

class Subscribe
{
    const ENDPOINT = 'sms.saveSubscribe';

    /** @var Client */
    protected $client;

    /** @var Config */
    protected $config;

    public function __construct(Client $client, Config $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * @param string $telephone
     * @param string $firstname
     * @param string $lastname
     * @param string $ip
     * @param array  $props
     * @return array
     */
    public function execute($telephone, $firstname = '', $lastname = '', $ip = '', $props = array())
    {
        $context = $this->client->createContext();
        $context->setEndpoint(self::ENDPOINT);

        $postParams = array(
            'list_id'   => $context->getListId(),
            'telephone' => $telephone,
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'ip'        => ($this->config->isSendUserIp() && !empty($ip)) ? $ip : '',
            'props'     => !empty($props) ? $props : '',
        );

        return $this->client->post($context, array(), $postParams);
    }
}
