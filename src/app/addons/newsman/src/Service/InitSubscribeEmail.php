<?php

namespace Tygh\Addons\Newsman\Service;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

class InitSubscribeEmail
{
    /** @var Client */
    protected $client;

    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    public function __construct(Client $client, Config $config, Logger $logger)
    {
        $this->client = $client;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param string $email
     * @param string $firstname
     * @param string $lastname
     * @param string $ip
     * @param array  $props
     * @param array  $options
     * @return array
     */
    public function execute($email, $firstname = '', $lastname = '', $ip = '', $props = array(), $options = array())
    {
        $this->logger->info(sprintf('Try to init subscribe email %s', $email));

        $context = $this->client->createContext();
        $context->setEndpoint('subscriber.initSubscribe');

        $postParams = array(
            'list_id'   => $context->getListId(),
            'email'     => $email,
            'firstname' => $firstname,
            'lastname'  => $lastname,
            'ip'        => !empty($ip) ? $ip : '',
            'props'     => !empty($props) ? $props : '',
            'options'   => !empty($options) ? $options : '',
        );

        $result = $this->client->post($context, array(), $postParams);

        $this->logger->info(sprintf('Init subscribe successful for email %s', $email));

        return $result;
    }
}
