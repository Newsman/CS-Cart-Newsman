<?php

namespace Tygh\Addons\Newsman\Action\Subscribe;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Service\InitSubscribeEmail;
use Tygh\Addons\Newsman\Service\SegmentAddSubscriber;
use Tygh\Addons\Newsman\Service\SubscribeEmail as SubscribeService;
use Tygh\Addons\Newsman\Service\UnsubscribeEmail;
use Tygh\Addons\Newsman\User\IpAddress;

class Email
{
    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var SubscribeService */
    protected $subscribeService;

    /** @var InitSubscribeEmail */
    protected $initSubscribeService;

    /** @var UnsubscribeEmail */
    protected $unsubscribeService;

    /** @var SegmentAddSubscriber */
    protected $segmentService;

    /** @var IpAddress */
    protected $ipAddress;

    public function __construct(
        Config $config,
        Logger $logger,
        SubscribeService $subscribeService,
        InitSubscribeEmail $initSubscribeService,
        UnsubscribeEmail $unsubscribeService,
        SegmentAddSubscriber $segmentService,
        IpAddress $ipAddress
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->subscribeService = $subscribeService;
        $this->initSubscribeService = $initSubscribeService;
        $this->unsubscribeService = $unsubscribeService;
        $this->segmentService = $segmentService;
        $this->ipAddress = $ipAddress;
    }

    /**
     * @param string $email
     * @param string $firstname
     * @param string $lastname
     * @param array  $properties
     * @param array  $options
     * @return bool
     */
    public function subscribe($email, $firstname = '', $lastname = '', $properties = array(), $options = array())
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning(sprintf('Invalid email for subscribe: %s', $email));
            return false;
        }

        $ip = $this->ipAddress->getIp();

        $this->logger->info(sprintf('Subscribing %s', $email));

        try {
            if ($this->config->isDoubleOptin()) {
                $result = $this->initSubscribeService->execute($email, $firstname, $lastname, $ip, $properties, $options);
            } else {
                $result = $this->subscribeService->execute($email, $firstname, $lastname, $ip, $properties);

                // Add to segment if configured
                $subscriberId = isset($result[0]) ? $result[0] : (isset($result['subscriber_id']) ? $result['subscriber_id'] : '');
                if (!empty($subscriberId) && !empty($this->config->getSegmentId())) {
                    $this->segmentService->execute($subscriberId);
                }
            }

            if (isset($result['error'])) {
                $this->logger->error(sprintf('Subscribe failed for %s: %s', $email, $result['error']));
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->logException($e);
            return false;
        }

        $this->logger->info(sprintf('Subscribed %s successfully', $email));
        return true;
    }

    /**
     * @param string $email
     * @return bool
     */
    public function unsubscribe($email)
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $ip = $this->ipAddress->getIp();

        $this->logger->info(sprintf('Unsubscribing %s', $email));

        try {
            $result = $this->unsubscribeService->execute($email, $ip);

            if (isset($result['error'])) {
                $this->logger->error(sprintf('Unsubscribe failed for %s: %s', $email, $result['error']));
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->logException($e);
            return false;
        }

        return true;
    }
}
