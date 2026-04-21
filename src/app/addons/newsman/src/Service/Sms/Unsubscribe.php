<?php

namespace Tygh\Addons\Newsman\Service\Sms;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;

class Unsubscribe
{
    const ENDPOINT = 'sms.saveUnsubscribe';

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
     * @param string $ip
     * @return array
     */
    public function execute($telephone, $ip = '')
    {
        $context = $this->client->createContext();
        $context->setEndpoint(self::ENDPOINT);

        $postParams = array(
            'list_id'   => $context->getListId(),
            'telephone' => $telephone,
            'ip'        => ($this->config->isSendUserIp() && !empty($ip)) ? $ip : '',
        );

        return $this->client->post($context, array(), $postParams);
    }
}
