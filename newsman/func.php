
application/x-httpd-php func.php ( PHP script, ASCII text )
<?php

/***************************************************************************
 *                                                                          *
 *   (c) 2017 Newsman                                                       *
 *                                                                          *
 * This  is  commercial  software,  only  users  who have purchased a valid *
 * license  and  accept  to the terms of the  License Agreement can install *
 * and use this program.                                                    *
 *                                                                          *
 ****************************************************************************
 *
 ****************************************************************************/

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

use Tygh\Registry;
use Tygh\Settings;

require_once(realpath(dirname(__FILE__)) . '/lib/Newsman/Client.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    //Execute if on settings page
    if($_POST["selected_section"] == "newsman_general")
    {
        
    try {
        $batchSize = 5000;

        $vars = Registry::get('addons.newsman');
        $userid = $vars['newsman_userid'];
        $apikey = $vars['newsman_apikey'];
        $listid = $vars['newsman_list'];
        $segmentid = $vars['newsman_segment'];

        $segmentidPost = 0;
        $listidPost = 0;
        $opts = $_POST["addon_data"]["options"];
        $c = 0;
        foreach ($opts as $key => $val) {
            $c++;
            if ($c == 5) {
                $listidPost = $opts[$key];
            }
            if ($c == 6) {
                $segmentidPost = $opts[$key];
                break;
            }
        }

        $importType = $vars['newsman_importType'];

        if (!empty($userid) || !empty($apikey)) {
            $client = new Newsman_Client($userid, $apikey);
            $lists = $client->list->all();
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            if($segmentidPost == 0){
                $segmentidPost = array();
            }
            else{
                $segmentidPost = array($segmentidPost);
            }

            if (empty($userid) || empty($apikey)) {
                fn_set_notification('W', 'Check fields', 'User Id and Api Key cannot be empty', 'S');
                return false;
            }

            if ($listidPost == 0) {
                fn_set_notification('W', 'Check fields', 'Select a list', 'S');
                return false;
            }

            if (empty($importType["importOrders"]) && empty($importType["importSubscribers"])) {
                fn_set_notification('W', 'Import Type', 'Please choose an import type (Subscribers or Customers), Save again to take effect', 'S');
                return false;
            }

            if ($importType["importOrders"] != "Y" && $importType["importSubscribers"] != "Y") {
                fn_set_notification('W', 'Import Type', 'Please choose an import type (Subscribers or Customers), Save again to take effect', 'S');
                return false;
            }

            if (empty($listid)) {
                fn_set_notification('W', 'List', 'List empty, save again to take effect', 'S');
                return false;
            }

            $vars = Registry::get('addons.newsman');
            $userid = $vars['newsman_userid'];
            $apikey = $vars['newsman_apikey'];
            $listid = $vars['newsman_list'];

            $client = new Newsman_Client($userid, $apikey);

            /*if ($importType["importSubscribers"] == "Y") {
                $customers_to_import = array();

                $users = db_query('SELECT * FROM ?:subscribers');

                foreach ($users as $user) {
                    $customers_to_import[] = array(
                        "email" => $user["email"],
                        //no name present
                        "name" => (empty($user['name']) ? " " : $user['name'])
                    );

                    if ((count($customers_to_import) % $batchSize) == 0) {
                        _importData($customers_to_import, $listidPost, $segmentidPost, $client, "cscart subscribers");
                    }
                }
                if (count($customers_to_import) > 0) {
                    _importData($customers_to_import, $listidPost, $segmentidPost, $client, "cscart subscribers");
                }

                unset($customers_to_import);
            }*/


            /*if ($importType["importOrders"] == "Y") {
                //Orders Processing

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
                        _importDataOrders($customers_to_import, $listidPost, $segmentidPost, $client, "cscart orders_completed");
                    }
                }

                if (count($customers_to_import) > 0) {
                    _importDataOrders($customers_to_import, $listidPost, $segmentidPost, $client, "cscart orders_completed");
                }

                unset($customers_to_import);
            }*/

            //fn_set_notification('S', 'Import', 'Import has been programmed successfully', 'S');
        }
    } catch (Exception $ex) {
        fn_set_notification('W', 'Credentials', 'User Id and Api Key are invalid, Save again to take effect, Error: ' . $ex->getMessage(), 'S');
        return false;
    }
    
  }

}

function safeForCsv($str)
{
    return '"' . str_replace('"', '""', $str) . '"';
}

function _importData(&$data, $list, $segments = null, $client, $source)
{
    $csv = '"email","fullname","source"' . PHP_EOL;

    foreach ($data as $_dat) {
        $csv .= sprintf(
            "%s,%s,%s",
            safeForCsv($_dat["email"]),
            safeForCsv($_dat["name"]),
            safeForCsv($source)
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

function _importDataOrders(&$data, $list, $segments = null, $client, $source)
{
    $csv = '"email","firstname", "lastname", "city", "source"' . PHP_EOL;

    foreach ($data as $_dat) {
        $csv .= sprintf(
            "%s,%s,%s,%s,%s",
            safeForCsv($_dat["email"]),
            safeForCsv($_dat["s_firstname"]),
            safeForCsv($_dat["s_lastname"]),
            safeForCsv($_dat["s_city"]),
            safeForCsv($source)
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

function cronTime($action, $value = null)
{
    $val = "";

    switch ($action) {
        case "Insert":
            $val = db_query('INSERT INTO ?:newsman_credentials (`time`) VALUES (' . $value . ');');
            break;
        case "Update":
            $val = db_query('UPDATE ?:newsman_credentials SET `time` = ' . $value . ';');
            break;
        case "Select":
            $val = db_query('SELECT `time` FROM ?:newsman_credentials');
            break;
    }

    return $val;
}

function fn_settings_variants_addons_newsman_newsman_segment()
{
    try {
        $vars = Registry::get('addons.newsman');
        $userid = $vars['newsman_userid'];
        $apikey = $vars['newsman_apikey'];
        $listid = $vars['newsman_list'];
        if (!empty($userid) || !empty($apikey)) {
            $client = new Newsman_Client($userid, $apikey);
            $segments = $client->segment->all($listid);
        }

        $all_datas = array();

        if (!empty($segments)) {
            foreach ($segments as $segment) {
                $all_datas[$segment['segment_id']] = $segment['segment_name'];
            }
        } else {
            $all_datas['0'] = 'Select and update list';
        }

    } catch (Exception $ex) {
        fn_set_notification('W', 'Credentials', 'Error: ' . $ex->getMessage(), 'S');
        return false;
    }

    return $all_datas;
}

function fn_settings_variants_addons_newsman_newsman_list()
{
    try {
        $vars = Registry::get('addons.newsman');
        $userid = $vars['newsman_userid'];
        $apikey = $vars['newsman_apikey'];

        if (!empty($userid) || !empty($apikey)) {
            $client = new Newsman_Client($userid, $apikey);
            $lists = $client->list->all();
        }

        $all_datas = array();

        if (!empty($lists)) {
            foreach ($lists as $list) {
                $all_datas[$list['list_id']] = $list['list_name'];
            }
        } else {
            $all_datas['0'] = 'Insert UserId and ApiKey';
        }

    } catch (Exception $ex) {
        fn_set_notification('W', 'Credentials', 'Error: ' . $ex->getMessage(), 'S');
        return false;
    }

    return $all_datas;
}

function fn_newsman_update_user_profile_post($user_id, $user_data, $action)
{
    /*OBSOLETE
    if ($action == "add") {
        $batchSize = 5000;

        $vars = Registry::get('addons.newsman');
        $userid = $vars['newsman_userid'];
        $apikey = $vars['newsman_apikey'];
        $listid = $vars['newsman_list'];
        $importType = $vars['newsman_importType'];
        $segmentid = $vars['newsman_segment'];

        $first = false;

        $time = cronTime("Select");
        if ($time->num_rows == 0) {
            cronTime("Insert", time());
            $first = true;
        }

        $time = cronTime("Select");

        foreach ($time as $_time) {
            $time = $_time["time"];
        }

        $timefromdatabase = $time;

        $dif = time() - $timefromdatabase;

        if (!$first) {
            if ($dif > 86400) {
                cronTime("Update", time());
            } else {
                die('cannot execute scripts, 24 hour must pass');
            }
        }

        if (empty($importType["importOrders"]) && empty($importType["importSubscribers"])) {
            fn_set_notification('W', 'Import Type', 'Please choose an import type (Subscribers or Customers)', 'S');
            return false;
        }

        if ($importType["importOrders"] != "Y" && $importType["importSubscribers"] != "Y") {
            fn_set_notification('W', 'Import Type', 'Please choose an import type (Subscribers or Customers)', 'S');
            return false;
        }

        if (!empty($userid) && !empty($apikey) && !empty($listid)) {
            $client = new Newsman_Client($userid, $apikey);
            $client->setCallType("rest");

            if ($importType["importSubscribers"] == "Y") {
                //Subscribers
                $customers_to_import = array();

                $users = db_query('SELECT * FROM ?:subscribers WHERE status = ?i', "A");

                foreach ($users as $user) {
                    $customers_to_import[] = array(
                        "email" => $user["email"],
                          //no name present
                        "name" => (empty($user['name']) ? " " : $user['name'])
                    );

                    if ((count($customers_to_import) % $batchSize) == 0) {
                        _importData($customers_to_import, $listid, array($segmentid), $client, "cscart subscribers");
                    }
                }
                if (count($customers_to_import) > 0) {
                    _importData($customers_to_import, $listid, array($segmentid), $client, "cscart subscribers");
                }

                unset($customers_to_import);
                //Subscribers
            }

            if ($importType["importOrders"] == "Y") {

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
                        _importDataOrders($customers_to_import, $listid, array($segmentid), $client, "cscart orders_completed");
                    }
                }
                if (count($customers_to_import) > 0) {
                    _importDataOrders($customers_to_import, $listid, array($segmentid), $client, "cscart orders_completed");
                }

                unset($customers_to_import);

            }
        }
    }
    */
}


?>
