<?php

namespace Tygh\Addons\Newsman\Export;

class Renderer
{
    /**
     * @param array $data
     * @param int   $statusCode
     */
    public function sendJson($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    }

    /**
     * @param array $data
     */
    public function sendSuccess($data)
    {
        $this->sendJson($data);
    }

    /**
     * @param string $message
     * @param int    $code
     * @param int    $httpStatus
     */
    public function sendError($message, $code = 1000, $httpStatus = 400)
    {
        $this->sendJson(
            array('error' => array('code' => $code, 'message' => $message)),
            $httpStatus
        );
    }
}
