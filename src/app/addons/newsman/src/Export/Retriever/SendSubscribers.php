<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Export\AbstractRetriever;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Service\ExportCsvSubscribers;

class SendSubscribers extends AbstractRetriever
{
    const BATCH_SIZE = 9000;

    /** @var ExportCsvSubscribers */
    protected $exportService;

    /** @var Subscribers */
    protected $subscribersRetriever;

    public function __construct(Config $config, Logger $logger, ExportCsvSubscribers $exportService)
    {
        parent::__construct($config, $logger);
        $this->exportService = $exportService;
        $this->subscribersRetriever = new Subscribers($config, $logger);
    }

    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        $this->logger->info('Send subscribers');

        $subscribers = $this->subscribersRetriever->process($data);

        if (empty($subscribers)) {
            return array('status' => 'No subscribers found.');
        }

        $total = count($subscribers);
        $listId = isset($data['list_id']) && $data['list_id'] !== ''
            ? (string) $data['list_id']
            : $this->config->getListId();
        $segmentId = isset($data['segment_id']) && $data['segment_id'] !== ''
            ? (string) $data['segment_id']
            : $this->config->getSegmentId();

        $batches = array_chunk($subscribers, self::BATCH_SIZE);
        $results = array();
        $sent = 0;

        foreach ($batches as $batch) {
            try {
                $csvRows = array();
                foreach ($batch as $sub) {
                    $csvRows[] = array(
                        'email'     => isset($sub['email']) ? $sub['email'] : '',
                        'firstname' => isset($sub['firstname']) ? $sub['firstname'] : '',
                        'lastname'  => isset($sub['lastname']) ? $sub['lastname'] : '',
                        'phone'     => isset($sub['phone']) ? $sub['phone'] : '',
                    );
                }

                $result = $this->exportService->execute($listId, $segmentId, $csvRows);
                $results[] = $result;
                $sent += count($batch);
            } catch (\Exception $e) {
                $this->logger->error('SendSubscribers batch error: ' . $e->getMessage());
                $results[] = array('error' => $e->getMessage());
            }
        }

        return array(
            'status'  => sprintf('Sent to NewsMAN %d subscribers out of total %d.', $sent, $total),
            'results' => $results,
        );
    }
}
