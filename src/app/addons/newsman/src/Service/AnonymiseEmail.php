<?php

namespace Tygh\Addons\Newsman\Service;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

/**
 * Anonymises an email address in the configured Newsman list
 * (subscriber.anonymiseEmail).
 *
 * Newsman accepts the request right away and anonymises the contact a few
 * seconds later. An email address that is not on the list is answered with
 * error code 121.
 */
class AnonymiseEmail
{
    /** Newsman error code: the email address is not on the list. */
    const ERROR_NO_SUCH_SUBSCRIBER = 121;

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
     * @return array|mixed The API result; an error result has error, code and message
     */
    public function execute($email)
    {
        $this->logger->info(sprintf('Try to anonymise email %s', $email));

        $context = $this->client->createContext();
        $context->setEndpoint('subscriber.anonymiseEmail');

        $postParams = array(
            'list_id' => $context->getListId(),
            'email'   => $email,
        );

        return $this->client->post($context, array(), $postParams);
    }
}
