<?php

namespace Tygh\Addons\Newsman\Api;

class Context
{
    /** @var string */
    protected $userId = '';

    /** @var string */
    protected $apiKey = '';

    /** @var string */
    protected $endpoint = '';

    /** @var string */
    protected $listId = '';

    /** @var string */
    protected $segmentId = '';

    /**
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param string $userId
     * @return $this
     */
    public function setUserId($userId)
    {
        $this->userId = (string) $userId;
        return $this;
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @param string $apiKey
     * @return $this
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = (string) $apiKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * @param string $endpoint
     * @return $this
     */
    public function setEndpoint($endpoint)
    {
        $this->endpoint = (string) $endpoint;
        return $this;
    }

    /**
     * @return string
     */
    public function getListId()
    {
        return $this->listId;
    }

    /**
     * @param string $listId
     * @return $this
     */
    public function setListId($listId)
    {
        $this->listId = (string) $listId;
        return $this;
    }

    /**
     * @return string
     */
    public function getSegmentId()
    {
        return $this->segmentId;
    }

    /**
     * @param string $segmentId
     * @return $this
     */
    public function setSegmentId($segmentId)
    {
        $this->segmentId = (string) $segmentId;
        return $this;
    }

    /**
     * @return string
     */
    public function buildUrl()
    {
        return sprintf(
            '%s%s/rest/%s/%s/%s.json',
            \Tygh\Addons\Newsman\Config::API_URL,
            \Tygh\Addons\Newsman\Config::API_VERSION,
            urlencode($this->userId),
            urlencode($this->apiKey),
            $this->endpoint
        );
    }
}
