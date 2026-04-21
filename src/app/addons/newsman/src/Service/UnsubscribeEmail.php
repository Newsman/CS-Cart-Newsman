<?php

namespace Tygh\Addons\Newsman\Service;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

class UnsubscribeEmail
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
     * @param string $ip
     * @return array
     */
    public function execute($email, $ip = '')
    {
        $this->logger->info(sprintf('Try to unsubscribe email %s', $email));

        $context = $this->client->createContext();
        $context->setEndpoint('subscriber.saveUnsubscribe');

        $postParams = array(
            'list_id' => $context->getListId(),
            'email'   => $email,
            'ip'      => !empty($ip) ? $ip : '',
        );

        $result = $this->client->post($context, array(), $postParams);

        $this->logger->info(sprintf('Unsubscribed email %s', $email));

        return $result;
    }
}
