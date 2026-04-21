<?php

namespace Tygh\Addons\Newsman\Export\V1;

class ApiV1Exception extends \RuntimeException
{
    /** @var int */
    protected $errorCode;

    /** @var int */
    protected $httpStatus;

    /**
     * @param int    $errorCode
     * @param string $message
     * @param int    $httpStatus
     */
    public function __construct($errorCode, $message, $httpStatus = 500)
    {
        $this->errorCode = (int) $errorCode;
        $this->httpStatus = (int) $httpStatus;
        parent::__construct($message);
    }

    /**
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @return int
     */
    public function getHttpStatus()
    {
        return $this->httpStatus;
    }
}
