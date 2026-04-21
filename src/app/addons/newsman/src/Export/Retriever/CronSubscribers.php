<?php

namespace Tygh\Addons\Newsman\Export\Retriever;

use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Service\ExportCsvSubscribers;

class CronSubscribers extends SendSubscribers
{
    const DEFAULT_PAGE_SIZE = 1000;

    public function __construct(Config $config, Logger $logger, ExportCsvSubscribers $exportService)
    {
        parent::__construct($config, $logger, $exportService);
    }

    /**
     * @param array $data
     * @return array
     */
    public function process($data = array())
    {
        // If limit already set, do a single page
        if (isset($data['limit'])) {
            return parent::process($data);
        }

        // Auto-paginate through all subscribers
        $count = $this->getSubscriberCount($data);
        if ($count === 0) {
            return array('status' => 'No subscribers found.');
        }

        $results = array();
        for ($start = 0; $start < $count; $start += self::DEFAULT_PAGE_SIZE) {
            $pageData = $data;
            $pageData['start'] = $start;
            $pageData['limit'] = self::DEFAULT_PAGE_SIZE;

            $result = parent::process($pageData);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * @param array $data
     * @return int
     */
    public function getSubscriberCount(array $data = array())
    {
        return $this->subscribersRetriever->countSubscribers($data);
    }
}
