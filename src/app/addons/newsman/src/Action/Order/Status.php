<?php

namespace Tygh\Addons\Newsman\Action\Order;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Service\SetPurchaseStatus;

class Status
{
    /** @var Config */
    protected $config;

    /** @var Logger */
    protected $logger;

    /** @var SetPurchaseStatus */
    protected $setPurchaseStatusService;

    /** @var StatusMapper */
    protected $statusMapper;

    public function __construct(Config $config, Logger $logger, SetPurchaseStatus $setPurchaseStatusService)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->setPurchaseStatusService = $setPurchaseStatusService;
        $this->statusMapper = new StatusMapper();
    }

    /**
     * @param int    $orderId
     * @param string $statusTo
     * @return bool
     */
    public function execute($orderId, $statusTo)
    {
        if (!$this->config->isEnabled()) {
            $this->logger->debug(sprintf('Order #%d status not updated — plugin disabled', $orderId));
            return false;
        }

        $newsmanStatus = $this->statusMapper->toNewsman($statusTo);
        $this->logger->info(sprintf('Updating order #%d status to %s (from CS-Cart status %s)', $orderId, $newsmanStatus, $statusTo));

        $result = $this->setPurchaseStatusService->execute($orderId, $newsmanStatus);

        if (isset($result['error'])) {
            $this->logger->error(sprintf('Failed to update order #%d status: %s', $orderId, $result['error']));
            return false;
        }

        $this->logger->info(sprintf('Order #%d status updated to %s', $orderId, $newsmanStatus));
        return true;
    }
}
