<?php

namespace Tygh\Addons\Newsman\Service;

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;

class ExportCsvSubscribers
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
     * @param string     $listId
     * @param string     $segmentId
     * @param array      $subscribers
     * @param array      $additionalFields
     * @return array
     */
    public function execute($listId, $segmentId, $subscribers, $additionalFields = array())
    {
        $context = $this->client->createContext();
        $context->setEndpoint('import.csv');

        $csvData = $this->serializeCsvData($subscribers, $additionalFields);

        $this->logger->info(sprintf('Exporting %d subscribers via import.csv', count($subscribers)));

        $segments = !empty($segmentId) ? array($segmentId) : null;

        $postParams = array(
            'list_id'  => $listId,
            'segments' => $segments,
            'csv_data' => $csvData,
        );

        $result = $this->client->post($context, array(), $postParams);

        if (isset($result['error'])) {
            throw new \RuntimeException('import.csv API error: ' . $result['error']);
        }

        $this->logger->info('Subscriber export completed successfully');

        return $result;
    }

    /**
     * @param array $subscribers
     * @param array $additionalFields
     * @return string
     */
    public function serializeCsvData($subscribers, $additionalFields = array())
    {
        $sendPhone = $this->config->isRemarketingSendTelephone();

        $headers = array('email', 'firstname', 'lastname');
        if ($sendPhone) {
            $headers = array_merge($headers, array('tel', 'phone', 'telephone', 'billing_telephone', 'shipping_telephone'));
        }
        $headers[] = 'source';
        $headers = array_merge($headers, $additionalFields);

        $lines = array();
        $lines[] = $this->csvLine($headers);

        foreach ($subscribers as $sub) {
            $row = array(
                isset($sub['email']) ? $sub['email'] : '',
                isset($sub['firstname']) ? $sub['firstname'] : '',
                isset($sub['lastname']) ? $sub['lastname'] : '',
            );

            if ($sendPhone) {
                $phone = isset($sub['phone']) ? $sub['phone'] : '';
                $row = array_merge($row, array($phone, $phone, $phone, $phone, $phone));
            }

            $row[] = 'CS-Cart';

            // Additional fields
            foreach ($additionalFields as $field) {
                $row[] = isset($sub['additional'][$field]) ? $sub['additional'][$field] : '';
            }

            $lines[] = $this->csvLine($row);
        }

        return implode("\n", $lines);
    }

    /**
     * @param array $values
     * @return string
     */
    public function csvLine($values)
    {
        $escaped = array();
        foreach ($values as $val) {
            $val = trim(str_replace('"', '', (string) $val));
            $escaped[] = '"' . $val . '"';
        }

        return implode(',', $escaped);
    }
}
