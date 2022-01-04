<?php

//ini_set('error_reporting', E_ALL);

use Tygh\Registry;
use Tygh\Settings;

require_once(__DIR__ . '/../../../app/addons/newsman/lib/Newsman/Client.php');

$vars = Registry::get('addons.newsman');
$_apikey = $vars['newsman_apikey'];

$importType = $vars['newsman_importType'];

$cron = (empty($_GET["cron"])) ? "" : $_GET["cron"];
$apikey = (empty($_GET["apikey"])) ? "" : $_GET["apikey"];
$newsman = (empty($_GET["newsman"])) ? "" : $_GET["newsman"];
$start = (!empty($_GET["start"]) && $_GET["start"] >= 0) ? $_GET["start"] : "";
$limit = (empty($_GET["limit"])) ? "" : $_GET["limit"];
$startLimit;
$order_id = (empty($_GET["order_id"])) ? "" : $_GET["order_id"];
$product_id = (empty($_GET["product_id"])) ? "" : $_GET["product_id"];
$cronLast = (empty($_GET["cronlast"])) ? "" : $_GET["cronlast"];
if(!empty($cronLast))
    $cronLast = ($cronLast == "true") ? true : false;

//by default display categories
$urlcategorybool = (!empty($_GET["urlcategorybool"]) && $_GET["urlcategorybool"] == "false") ? false : true;
$urlextensionstring = (empty($_GET["urlextensionstring"])) ? "" : $_GET["urlextensionstring"];

if(!empty($start) && $start >= 0 && !empty($limit))
$startLimit = " LIMIT {$limit} OFFSET {$start}";

newsmanGetCart();

//API
if (!empty($newsman) && !empty($apikey) && empty($cron)) {
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

    try{
    
        switch ($newsman) {
            case "orders.json":

                $ordersObj = array();            

                $query = db_query('SELECT * FROM ?:orders' . $startLimit);

                if(!empty($order_id))
                    $query = db_query('SELECT * FROM ?:orders WHERE order_id = ?i', $order_id);

                $orders = $query;

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

                        $currProdPrices = db_query('SELECT * FROM ?:product_prices WHERE product_id = ?i', $p["product_id"]);
                        foreach ($currProdPrices as $_currProd) {
                            $currProdPrices = $_currProd;
                        }                      

                        $image_url = "";

                        $imageData = db_get_array("SELECT ?:images.image_path, ?:images_links.type FROM ?:images ".
                        "LEFT JOIN ?:images_links ON ?:images_links.detailed_id = ?:images.image_id ".
                        "WHERE ?:images_links.object_id = ?s", $p['product_id']);

                        foreach ($imageData as $k => $v) {                   
                    
                            $images = fn_get_image_pairs($p['product_id'], 'product', $v['type'], false, true, $lang_code);
                        
                            if ($v['type'] == 'M') {
                                    $image_url = $images['detailed']['image_path'];
                            }                                                             
                        
                        }

                        $oldUrl = 'https://' . getenv('HTTP_HOST') . "?dispatch=products.view?product_id=" . $p["product_id"];
                        $url = 'https://' . getenv('HTTP_HOST') . '/';
                        $seoName = fn_seo_get_name('p', $p["product_id"]);
                        $catExplode = "";   
                        $catName = "";
    
                        $path = db_get_hash_single_array(
                        "SELECT c.id_path, p.link_type FROM ?:categories as c LEFT JOIN ?:products_categories as p ON p.category_id = c.category_id WHERE p.product_id = ?i ?p",
                        array('link_type', 'id_path'),
                        $p["product_id"],
                        fn_get_seo_company_condition('c.company_id', '', 0)
                    );
        
                    if (!empty($path['M'])) {
                        $catExplode = $path['M'];
                    } elseif (!empty($path['A'])) {
                        $catExplode = $path['A'];
                    }
    
                    $catExplode = explode("/", $catExplode);
    
                    foreach($catExplode as $id){
                        $c = db_query('SELECT * FROM ?:category_descriptions WHERE category_id = ?i', $id);                    
                        foreach($c as $cat)
                        {
                            $catName .= strtolower($cat["category"] . '/');
                        }                    
                    }
    
                    if($urlcategorybool)
                    {
                        $url .= $catName . $seoName;
                    }
                    else{
                        $url .= $seoName;
                    }

                    $url = str_replace(" & ", " ", $url);
                    $url = str_replace(" ", "-", $url);
                    
                    if(!empty($urlextensionstring))
                        $url .= '.' . $urlextensionstring;

                        $productsJson[] = array(
                            "id" => $currProdM["product_id"],
                            "name" => $currProd["product"],
                            "stock_quantity" => (int)$currProdM["amount"],
                            "price" => (float)$currProdPrices["price"],
                            "price_old" => (float)$currProdM["list_price"],
                            "image_url" => $image_url,
                            "url" => $url
                        );
                    }

                    $status = "";

                    switch($item["status"]){
                        case "C":
                            $status = "completed";
                        break;
                        case "P":
                            $status = "processed";
                        break;                
                        case "O":
                            $status = "opened";
                        break;               
                        case "F":
                            $status = "failed";
                        break;                
                        case "C":
                            $status = "cancelled";
                        break;                
                        case "D":
                            $status = "declined";
                        break;     
                        case "A":
                            $stauts = "delivering";
                        break;
                        case "H":
                            $status = "client not responding";    
                        break;
                        case "E":
                            $status = "confirmed payment";
                        break;
                        case "I":
                            $status = "cancelled";
                        break;
                    }       

                    $ordersObj[] = array(
                        "order_no" => $item["order_id"],  
                        "date" => $item["timestamp"],         
                        "status" => $status,     
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
                        "total" => (float)$item["total"],
                        "products" => $productsJson
                    );
                }

                header('Content-Type: application/json');
                echo json_encode($ordersObj, JSON_PRETTY_PRINT);
                exit();      

                break;

            case
            "products.json":        
        
                $query = db_query('SELECT * FROM ?:products' . $startLimit);

                if(!empty($product_id))
                    $query = db_query('SELECT * FROM ?:products WHERE product_id = ?i', $product_id);

                $products = $query;
                $productsJson = array();

                foreach ($products as $prod) {
                    $currProd = db_query('SELECT * FROM ?:product_descriptions WHERE product_id = ?i', $prod["product_id"]);
                    foreach ($currProd as $_currProd) {
                        $currProd = $_currProd;
                    }

                    $currProdPrices = db_query('SELECT * FROM ?:product_prices WHERE product_id = ?i', $prod["product_id"]);
                    foreach ($currProdPrices as $_currProd) {
                        $currProdPrices = $_currProd;
                    }                        

                    $image_url = "";

                    $imageData = db_get_array("SELECT ?:images.image_path, ?:images_links.type FROM ?:images ".
                    "LEFT JOIN ?:images_links ON ?:images_links.detailed_id = ?:images.image_id ".
                    "WHERE ?:images_links.object_id = ?s", $prod['product_id']);

                    foreach ($imageData as $k => $v) {                   

                        $images = fn_get_image_pairs($prod['product_id'], 'product', $v['type'], false, true, $lang_code);
                    
                        if ($v['type'] == 'M') {
                                $image_url = $images['detailed']['image_path'];
                        }                                                             
                    
                    }

                    $oldUrl = 'https://' . getenv('HTTP_HOST') . "?dispatch=products.view?product_id=" . $prod["product_id"];
                    $url = 'https://' . getenv('HTTP_HOST') . '/';
                    $seoName = fn_seo_get_name('p', $prod["product_id"]);
                    $catExplode = "";   
                    $catName = "";

                    $path = db_get_hash_single_array(
                        "SELECT c.id_path, p.link_type FROM ?:categories as c LEFT JOIN ?:products_categories as p ON p.category_id = c.category_id WHERE p.product_id = ?i ?p",
                        array('link_type', 'id_path'),
                        $prod["product_id"],
                        fn_get_seo_company_condition('c.company_id', '', 0)
                    );
        
                    if (!empty($path['M'])) {
                        $catExplode = $path['M'];
                    } elseif (!empty($path['A'])) {
                        $catExplode = $path['A'];
                    }

                    $catExplode = explode("/", $catExplode);

                    foreach($catExplode as $id){
                        $c = db_query('SELECT * FROM ?:category_descriptions WHERE category_id = ?i', $id);                    
                        foreach($c as $cat)
                        {
                            $catName .= strtolower($cat["category"] . '/');
                        }                    
                    }

                    if($urlcategorybool)
                    {
                        $url .= $catName . $seoName;
                    }
                    else{
                        $url .= $seoName;
                    }

                    $url = str_replace(" & ", " ", $url);
                    $url = str_replace(" ", "-", $url);
                    
                    if(!empty($urlextensionstring))
                        $url .= '.' . $urlextensionstring;

                    $productsJson[] = array(
                        "id" => $prod["product_id"],
                        "name" => $currProd["product"],
                        "stock_quantity" => (int)$prod["amount"],
                        "price" => (float)$currProdPrices["price"],
                        "price_old" => (float)$prod["list_price"],
                        "image_url" => $image_url,
                        "url" => $url
                    );
                }

                header('Content-Type: application/json');
                echo json_encode($productsJson, JSON_PRETTY_PRINT);
                exit();                 

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
                exit();
    
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
                exit();

                break;
            case "count.json":

                    $orders = db_query('SELECT COUNT(*) FROM ?:orders WHERE status = ?i', "C");
                    
                    foreach($orders as $o)
                    {
                        $orders = $o["COUNT(*)"];
                    }
            
                    $users = db_query('SELECT COUNT(*) FROM ?:subscribers');
            
                    foreach($users as $o)
                    {
                        $users = $o["COUNT(*)"];
                    }
            
                    header('Content-Type: application/json');
                    echo json_encode(array(
                        'orders_completed' => $orders,
                        'subscribers' => $users
                    )
                );
                exit();
            
                break;
            
            case "version.json":

                $version = array(
                    "version" => "CsCart " . PRODUCT_VERSION
                    );    

                header('Content-Type: application/json');
                echo json_encode($version);
                exit();

            break;
        }
    }
    catch(Exception $ex)
    {
      $status = array(
          "status" => "error",
          "message" => $ex->getMessage()
      );

      header('Content-Type: application/json');
      echo json_encode($productsJson, JSON_PRETTY_PRINT);
      exit;  
    }
} 
//CRON
elseif(!empty($cron) && !empty($apikey) && !empty($newsman))
{   
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

    $batchSize = 9999;

    $vars = Registry::get('addons.newsman');
    $userid = $vars['newsman_userid'];
    $apikey = $vars['newsman_apikey'];
    $listid = $vars['newsman_list'];
    $importType = $vars['newsman_importType'];
    $segmentid = $vars['newsman_segment'];

    $segment = null;
    
    if($segmentid == 0){
        $segment = array();
    }
    else{
        $segment = array($segmentid);
    }

    if (!empty($userid) && !empty($apikey) && !empty($listid)) {
        $client = new Newsman_Client($userid, $apikey);
        $client->setCallType("rest");    

     switch ($newsman) {

        case "subscribers":         

        try{
            //Subscribers
            
            if($cronLast)
            {
                $users = db_query('SELECT * FROM ?:em_subscribers');  

                $data = $users->num_rows;

                $start = $data - (int)$limit;

                if($start < 1)
                {
                    $start = 1;
                }       
            }

            
            $customers_to_import = array();

            $users = db_query('SELECT * FROM ?:em_subscribers WHERE status = ?i' . $startLimit, "A");

            foreach ($users as $user) {
                $customers_to_import[] = array(
                    "email" => $user["email"],
                      //no name present
                    "name" => (empty($user['name']) ? " " : $user['name'])
                );

                if ((count($customers_to_import) % $batchSize) == 0) {
                    _importDataCRON($customers_to_import, $listid, $segment, $client, "cscart subscribers CRON");
                }
            }
            if (count($customers_to_import) > 0) {
                _importDataCRON($customers_to_import, $listid, $segment, $client, "cscart subscribers CRON");
            }

            unset($customers_to_import);

            //Subscribers       
        }
        catch(Exception $ex){
            //table not found (optional)
            echo "(Optional) table em_subscribers not found, Message: " . $ex->getMessage();
        } 

        try{
            //Subscribers

            if($cronLast)
            {
                $users = db_query('SELECT * FROM ?:subscribers');  

                $data = $users->num_rows;

                $start = $data - (int)$limit;

                if($start < 1)
                {
                    $start = 1;
                }       
            }

            $customers_to_import = array();

            $users = db_query('SELECT * FROM ?:subscribers' . $startLimit);            

            foreach ($users as $user) {
                $customers_to_import[] = array(
                    "email" => $user["email"],
                      //no name present
                    "name" => ''
                );

                if ((count($customers_to_import) % $batchSize) == 0) {
                    _importDataCRON($customers_to_import, $listid, $segment, $client, "cscart subscribers CRON");
                }
            }
            if (count($customers_to_import) > 0) {
                _importDataCRON($customers_to_import, $listid, $segment, $client, "cscart subscribers CRON");
            }

            unset($customers_to_import);

            //Subscribers       
        }
        catch(Exception $ex){
            //table not found (optional)
            echo "table subscribers not found, error: " . $ex->getMessage();
        } 

        header('Content-Type: application/json');
        echo json_encode(array('status' => "subscribers sync ok"));
        exit();

    break;

    case "orders":
        
            /*Orders Processing*/
             
            try{

                if($cronLast)
                {
                    $orders = db_query('SELECT * FROM ?:orders WHERE status = ?i', "C");

                    $data = $orders->num_rows;             

                    $start = $data - (int)$limit;

                    if($start < 1)
                    {
                        $start = 1;
                    }                            
                }

                $customers_to_import = array();

                $orders = db_query('SELECT * FROM ?:orders WHERE status = ?i' . $startLimit, "C");

                foreach ($orders as $order) {
                    $customers_to_import[] = array(
                        "email" => $order["email"],
                        "s_firstname" => (empty($order['s_firstname']) ? " " : $order['s_firstname']),
                        "s_lastname" => (empty($order['s_lastname']) ? " " : $order['s_lastname']),
                        "s_city" => (empty($order['s_city']) ? " " : $order['s_city'])
                    );

                    if ((count($customers_to_import) % $batchSize) == 0) {
                        _importDataCRONOrders($customers_to_import, $listid, $segment, $client, "cscart orders_completed CRON");
                    }
                }
                if (count($customers_to_import) > 0) {
                    _importDataCRONOrders($customers_to_import, $listid, $segment, $client, "cscart orders_completed CRON");
                }

                unset($customers_to_import);
                
                /*Orders Processing*/   
                
                header('Content-Type: application/json');
                echo json_encode(array('status' => "orders completed sync ok"));
                exit();

            }
          catch(Exception $ex)
          {
            $status = array(
                "status" => "error",
                "message" => $ex->getMessage()
            );
              
            header('Content-Type: application/json');
            echo json_encode($productsJson, JSON_PRETTY_PRINT);  
            exit();         
          }
                
        break;

        }

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
    exit();
}

function newsmanGetCart()
{    			         			
    $newsman = (empty($_GET["newsman"])) ? "" : $_GET["newsman"];                              
    
    if (!empty($newsman) &&  (bool)$_POST["post"] == true) {              
         
        switch ($_GET["newsman"]) {
            case "getCart.json":                        

                $cart = $_SESSION["cart"]["products"];                
                
                $prod = array();

                foreach ($cart as $cart_item_key => $cart_item ) {

                    $prod[] = array(
                        "id" => $cart_item['product_id'],
                        "name" => $cart_item["product"],
                        "price" => $cart_item["price"],						
                        "quantity" => $cart_item['amount']
                    );							
                                            
                 }									 						

                header('Content-Type: application/json');
                echo json_encode($prod, JSON_PRETTY_PRINT);                
                exit();

                break; 
            default:      
                header('Content-Type: application/json');
                echo json_encode(array("status" => 0, "message" => "bad url"), JSON_PRETTY_PRINT);     
                exit();   
            break;
        }

    }			
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
