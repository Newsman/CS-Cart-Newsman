<?php

namespace Tygh\Addons\Newsman\Service\Sms;

use Tygh\Addons\Newsman\Api\Client;

class SendOne
{
    const ENDPOINT = 'sms.sendone';

    /** @var Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param string $to
     * @param string $text
     * @return array
     */
    public function execute($to, $text)
    {
        $context = $this->client->createContext();
        $context->setEndpoint(self::ENDPOINT);

        $postParams = array(
            'list_id' => $context->getListId(),
            'to'      => $to,
            'text'    => $text,
        );

        return $this->client->post($context, array(), $postParams);
    }
}
