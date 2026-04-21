<?php

use Tygh\Tygh;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

/** @var string $mode */

if ($mode === 'api') {
    $router = Tygh::$app['addons.newsman.export.router'];
    $router->execute();
    exit;
}

if ($mode === 'webhook') {
    $webhooks = Tygh::$app['addons.newsman.webhooks'];
    $rawInput = file_get_contents('php://input');
    $webhooks->execute($rawInput);
    exit;
}

if ($mode === 'identify') {
    $payload = array('email' => '', 'first_name' => '', 'last_name' => '');

    $auth = isset(Tygh::$app['session']['auth']) ? Tygh::$app['session']['auth'] : array();
    if (!empty($auth['user_id'])) {
        $userInfo = fn_get_user_info((int) $auth['user_id']);
        if (!empty($userInfo['email'])) {
            $payload['email'] = (string) $userInfo['email'];
            $payload['first_name'] = isset($userInfo['firstname']) ? (string) $userInfo['firstname'] : '';
            $payload['last_name'] = isset($userInfo['lastname']) ? (string) $userInfo['lastname'] : '';
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload);
    exit;
}

if ($mode === 'cart') {
    $products = array();
    $cart = isset(Tygh::$app['session']['cart']) ? Tygh::$app['session']['cart'] : array();

    if (!empty($cart['products'])) {
        foreach ($cart['products'] as $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            if (empty($productId)) {
                continue;
            }

            $productData = fn_get_product_data($productId, Tygh::$app['session']['auth']);

            $products[] = array(
                'id'       => (string) $productId,
                'name'     => isset($productData['product']) ? $productData['product'] : '',
                'price'    => number_format((float) (isset($item['price']) ? $item['price'] : 0), 2, '.', ''),
                'quantity' => (int) (isset($item['amount']) ? $item['amount'] : 1),
            );
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($products);
    exit;
}
