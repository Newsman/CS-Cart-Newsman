<?php

use Tygh\Registry;
use Tygh\Settings;

$vars = Registry::get('addons.newsman');
$_apikey = $vars['newsman_apikey'];

$importType = $vars['newsman_importType'];

$apikey = (empty($_GET["apikey"])) ? "" : $_GET["apikey"];
$newsman = (empty($_GET["newsman"])) ? "" : $_GET["newsman"];
$start = (!empty($_GET["start"]) && $_GET["start"] >= 0) ? $_GET["start"] : "";
$limit = (empty($_GET["limit"])) ? "" : $_GET["limit"];
$startLimit;

if (!empty($newsman) && !empty($apikey)) {
    $apikey = $_GET["apikey"];
    $currApiKey = $_apikey;

    if ($apikey != $currApiKey) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(array('status' => "403"));
        exit;
    }
    elseif(empty($importType["allowAPI"]))
    {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(array('status' => "403"));
        exit;
    }

    if(!empty($start) && $start >= 0 && !empty($limit))
    $startLimit = " LIMIT {$limit} OFFSET {$start}";

    switch ($_GET["newsman"]) {
        case "orders.json":

            $ordersObj = array();            

            $orders = db_query('SELECT * FROM ?:orders' . $startLimit);

            foreach ($orders as $item) {

                $productsD = db_query('SELECT * FROM ?:order_details WHERE order_id = ?i', $item["order_id"]);

                $productsJson = array();

                foreach ($productsD as $p) {
                    $currProdM = db_query('SELECT * FROM ?:products WHERE product_id = ?i', $p["product_id"]);
                    foreach ($currProdM as $_currProdM) {
                        $currProdM = $_currProdM;
                    }

                    $currProd = db_query('SELECT * FROM ?:product_descriptions WHERE product_id = ?i', $currProdM["product_id"]);
                    foreach ($currProd as $_currProd) {
                        $currProd = $_currProd;
                    }

                    $productsJson[] = array(
                        "id" => $currProdM["product_id"],
                        "name" => $currProd["product"],
                        "stock_quantity" => $currProdM["amount"],
                        "price" => $currProdM["list_price"]
                    );
                }

                $ordersObj[] = array(
                    "order_no" => $item["order_id"],
                    "lastname" => $item["firstname"],
                    "firstname" => $item["firstname"],
                    "email" => $item["email"],
                    "phone" => "",
                    "state" => "",
                    "city" => "",
                    "address" => "",
                    "discount" => "",
                    "discount_code" => "",
                    "shipping" => "",
                    "fees" => 0,
                    "rebates" => 0,
                    "total" => $item["total"],
                    "products" => $productsJson
                );
            }

            header('Content-Type: application/json');
            echo json_encode($ordersObj, JSON_PRETTY_PRINT);
            exit;      

            break;

        case
        "products.json":

            $products = db_query('SELECT * FROM ?:products' . $startLimit);
            $productsJson = array();

            foreach ($products as $prod) {
                $currProd = db_query('SELECT * FROM ?:product_descriptions WHERE product_id = ?i', $prod["product_id"]);
                foreach ($currProd as $_currProd) {
                    $currProd = $_currProd;
                }

                $productsJson[] = array(
                    "id" => $prod["product_id"],
                    "name" => $currProd["product"],
                    "stock_quantity" => $prod["amount"],
                    "price" => $prod["list_price"]
                );
            }

            header('Content-Type: application/json');
            echo json_encode($productsJson, JSON_PRETTY_PRINT);
            exit;     

            break;

        case "customers.json":

            $wp_cust = db_query('SELECT * FROM ?:orders WHERE status = ?i' . $startLimit, "C");

            $custs = array();

            foreach ($wp_cust as $users) {

                $custs[] = array(
                    "email" => $users["email"],
                    "firstname" => $users["firstname"],
                    "lastname" => $users["lastname"]
                );
            }

            header('Content-Type: application/json');
            echo json_encode($custs, JSON_PRETTY_PRINT);
            exit;
 
            break;

        case "subscribers.json":

            $wp_subscribers = db_query('SELECT * FROM ?:subscribers WHERE status = ?i' . $startLimit, "A");
            $subs = array();

            foreach ($wp_subscribers as $users) {
                $subs[] = array(
                    "email" => $users["email"],
                    "firstname" => "",
                    "lastname" => ""
                );
            }

            header('Content-Type: application/json');
            echo json_encode($subs, JSON_PRETTY_PRINT);
            exit;

            break;
    }
} else {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(array('status' => "403"));
}

exit;