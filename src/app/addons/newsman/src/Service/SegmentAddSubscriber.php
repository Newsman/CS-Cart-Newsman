<?php

namespace Tygh\Addons\Newsman\Service;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

class SegmentAddSubscriber
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
     * @param string $subscriberId
     * @return array
     */
    public function execute($subscriberId)
    {
        $segmentId = $this->config->getSegmentId();

        if (empty($segmentId)) {
            $this->logger->info('Segment add skipped: no segment ID configured');
            return array();
        }

        $this->logger->info(sprintf('Try to add subscriber %s to segment %s', $subscriberId, $segmentId));

        $context = $this->client->createContext();
        $context->setEndpoint('segment.addSubscriber');

        $postParams = array(
            'list_id'       => $context->getListId(),
            'segment_id'    => $segmentId,
            'subscriber_id' => $subscriberId,
        );

        $result = $this->client->post($context, array(), $postParams);

        $this->logger->info(sprintf('Added subscriber %s to segment %s', $subscriberId, $segmentId));

        return $result;
    }
}
