<?php

ini_set('error_reporting', E_ALL);

use Tygh\Registry;
use Tygh\Settings;

//require_once(realpath(dirname(__FILE__)) . '/lib/Newsman/Client.php');

$vars = Registry::get('addons.newsman');
$_apikey = $vars['newsman_apikey'];

$importType = $vars['newsman_importType'];

$cron = (empty($_GET["cron"])) ? "" : $_GET["cron"];
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
} 
elseif(!empty($cron) && !empty($apikey))
{
    $apikey = $_GET["apikey"];
    $currApiKey = $_apikey;

    if ($apikey != $currApiKey) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(array('status' => "403"));
        exit;
    }

    $batchSize = 5000;

    $vars = Registry::get('addons.newsman');
    $userid = $vars['newsman_userid'];
    $apikey = $vars['newsman_apikey'];
    $listid = $vars['newsman_list'];
    $importType = $vars['newsman_importType'];
    $segmentid = $vars['newsman_segment'];

    if (!empty($userid) && !empty($apikey) && !empty($listid)) {
        $client = new Newsman_Client($userid, $apikey);
        $client->setCallType("rest");

        try{
            //Subscribers
            $customers_to_import = array();

            $users = db_query('SELECT * FROM ?:em_subscribers WHERE status = ?i', "A");

            foreach ($users as $user) {
                $customers_to_import[] = array(
                    "email" => $user["email"],
                      //no name present
                    "name" => (empty($user['name']) ? " " : $user['name'])
                );

                if ((count($customers_to_import) % $batchSize) == 0) {
                    _importDataCRON($customers_to_import, $listid, array($segmentid), $client, "cscart subscribers CRON");
                }
            }
            if (count($customers_to_import) > 0) {
                _importDataCRON($customers_to_import, $listid, array($segmentid), $client, "cscart subscribers CRON");
            }

            unset($customers_to_import);
            //Subscribers       
        }
        catch(Exception $ex){
            //table not found (optional)
        } 

        try{
            //Subscribers
            $customers_to_import = array();

            $users = db_query('SELECT * FROM ?:subscribers');

            foreach ($users as $user) {
                $customers_to_import[] = array(
                    "email" => $user["email"],
                      //no name present
                    "name" => ''
                );

                if ((count($customers_to_import) % $batchSize) == 0) {
                    _importDataCRON($customers_to_import, $listid, array($segmentid), $client, "cscart subscribers CRON");
                }
            }
            if (count($customers_to_import) > 0) {
                _importDataCRON($customers_to_import, $listid, array($segmentid), $client, "cscart subscribers CRON");
            }

            unset($customers_to_import);
            //Subscribers       
        }
        catch(Exception $ex){
            //table not found (optional)
        } 
        
            /*Orders Processing*/
            $customers_to_import = array();

            $orders = db_query('SELECT * FROM ?:orders WHERE status = ?i', "C");

            foreach ($orders as $order) {
                $customers_to_import[] = array(
                    "email" => $order["email"],
                    "s_firstname" => (empty($order['s_firstname']) ? " " : $order['s_firstname']),
                    "s_lastname" => (empty($order['s_lastname']) ? " " : $order['s_lastname']),
                    "s_city" => (empty($order['s_city']) ? " " : $order['s_city'])
                );

                if ((count($customers_to_import) % $batchSize) == 0) {
                    _importDataCRONOrders($customers_to_import, $listid, array($segmentid), $client, "cscart orders_completed CRON");
                }
            }
            if (count($customers_to_import) > 0) {
                _importDataCRONOrders($customers_to_import, $listid, array($segmentid), $client, "cscart orders_completed CRON");
            }

            unset($customers_to_import);
            /*Orders Processing*/   
            
            echo "CRON";
    }
    else{
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(array('status' => "setup not completed"));
    }
}
else {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(array('status' => "403"));
}

function safeForCsvCRON($str)
{
    return '"' . str_replace('"', '""', $str) . '"';
}

function _importDataCRON(&$data, $list, $segments = null, $client, $source)
{
    $csv = '"email","fullname","source"' . PHP_EOL;

    foreach ($data as $_dat) {
        $csv .= sprintf(
            "%s,%s,%s",
            safeForCsvCRON($_dat["email"]),
            safeForCsvCRON($_dat["name"]),
            safeForCsvCRON($source)
        );
        $csv .= PHP_EOL;
    }

    $ret = null;
    try {
        if (is_array($segments) && count($segments) > 0) {
            $ret = $client->import->csv($list, $segments, $csv);
        } else {
            $ret = $client->import->csv($list, array(), $csv);
        }

        if ($ret == "") {
            throw new Exception("Import failed");
        }
    } catch (Exception $e) {

    }

    $data = array();
}

function _importDataCRONOrders(&$data, $list, $segments = null, $client, $source)
{
    $csv = '"email","firstname", "lastname", "city", "source"' . PHP_EOL;

    foreach ($data as $_dat) {
        $csv .= sprintf(
            "%s,%s,%s,%s,%s",
            safeForCsvCRON($_dat["email"]),
            safeForCsvCRON($_dat["s_firstname"]),
            safeForCsvCRON($_dat["s_lastname"]),
            safeForCsvCRON($_dat["s_city"]),
            safeForCsvCRON($source)
        );
        $csv .= PHP_EOL;
    }

    $ret = null;
    try {
        if (is_array($segments) && count($segments) > 0) {
            $ret = $client->import->csv($list, $segments, $csv);
        } else {
            $ret = $client->import->csv($list, array(), $csv);
        }

        if ($ret == "") {
            throw new Exception("Import failed");
        }
    } catch (Exception $e) {

    }

    $data = array();
}

exit;