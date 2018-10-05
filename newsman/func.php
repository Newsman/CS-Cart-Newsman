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

if (!defined('BOOTSTRAP'))
{
	die('Access denied');
}

use Tygh\Registry;
use Tygh\Settings;

require_once(realpath(dirname(__FILE__)) . '/lib/Newsman/Client.php');

function safeForCsv($str)
{
	return '"' . str_replace('"', '""', $str) . '"';
}

function _importData(&$data, $list, $segments = null, $client, $source)
{
	$csv = '"email","name","source"' . PHP_EOL;

	foreach ($data as $_dat)
	{
		$csv .= sprintf(
			"%s,%s,%s",
			safeForCsv($_dat["email"]),
			safeForCsv($_dat["name"]),
			safeForCsv($source)
		);
		$csv .= PHP_EOL;
	}

	$ret = null;
	try
	{
		if (is_array($segments) && count($segments) > 0)
		{
			$ret = $client->import->csv($list, $segments, $csv);
		} else
		{
			$ret = $client->import->csv($list, array(), $csv);
		}

		if ($ret == "")
		{
			throw new Exception("Import failed");
		}
	} catch (Exception $e)
	{

	}

	$data = array();
}

function cronTime($action, $value = null)
{
	$val = "";

	switch ($action)
	{
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

function fn_settings_variants_addons_newsman_newsman_list()
{
	try
	{
		$batchSize = 5000;

		$vars = Registry::get('addons.newsman');
		$userid = $vars['newsman_userid'];
		$apikey = $vars['newsman_apikey'];
		$listid = $vars['newsman_list'];
		$importType = $vars['newsman_importType'];

		if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			if (empty($userid) || empty($apikey))
			{
				fn_set_notification('W', 'Check fields', 'User Id and Api Key cannot be empty', 'S');
				return false;
			}

			if (empty($importType["importOrders"]) && empty($importType["importSubscribers"]))
			{
				fn_set_notification('W', 'Import Type', 'Please choose an import type (Subscribers or Customers), Save again to take effect', 'S');
				return false;
			}

			if ($importType["importOrders"] != "Y" && $importType["importSubscribers"] != "Y")
			{
				fn_set_notification('W', 'Import Type', 'Please choose an import type (Subscribers or Customers), Save again to take effect', 'S');
				return false;
			}
		}

		if (!empty($userid) || !empty($apikey))
		{
			$client = new Newsman_Client($userid, $apikey);
			$lists = $client->list->all();
		}

		$all_datas = array();

		if (!empty($lists))
		{
			foreach ($lists as $list)
			{
				$all_datas[$list['list_id']] = $list['list_name'];
			}
		} else
		{
			$all_datas['0'] = 'Insert UserId and ApiKey';
		}

		if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			if (empty($listid))
			{
				fn_set_notification('W', 'List', 'List empty, save again to take effect', 'S');
				return false;
			}

			$vars = Registry::get('addons.newsman');
			$userid = $vars['newsman_userid'];
			$apikey = $vars['newsman_apikey'];
			$listid = $vars['newsman_list'];

			$client = new Newsman_Client($userid, $apikey);

			if ($importType["importSubscribers"] == "Y")
			{
				$customers_to_import = array();

				$users = db_query('SELECT * FROM ?:em_subscribers WHERE status = ?i', "A");

				foreach ($users as $user)
				{
					$customers_to_import[] = array(
						"email" => $user["email"],
						"name" => (empty($user['name']) ? " " : $user['name'])
					);

					if ((count($customers_to_import) % $batchSize) == 0)
					{
						_importData($customers_to_import, $listid, null, $client, "cscart subscribers");
					}
				}
				if (count($customers_to_import) > 0)
				{
					_importData($customers_to_import, $listid, null, $client, "cscart subscribers");
				}

				unset($customers_to_import);
			}


			if ($importType["importOrders"] == "Y")
			{
				/*Orders Processing*/

				$customers_to_import = array();

				$orders = db_query('SELECT * FROM ?:orders WHERE status = ?i', "C");

				foreach ($orders as $order)
				{
					$customers_to_import[] = array(
						"email" => $order["email"],
						"name" => (empty($order['s_firstname']) ? " " : $order['s_firstname'])
					);

					if ((count($customers_to_import) % $batchSize) == 0)
					{
						_importData($customers_to_import, $listid, null, $client, "cscart orders_completed");
					}
				}
				if (count($customers_to_import) > 0)
				{
					_importData($customers_to_import, $listid, null, $client, "cscart orders_completed");
				}

				unset($customers_to_import);
			}

			fn_set_notification('S', 'Import', 'Import has been programmed successfully', 'S');
		}
	} catch (Exception $ex)
	{
		fn_set_notification('W', 'Credentials', 'User Id and Api Key are invalid, Save again to take effect', 'S');
		return false;
	}

	return $all_datas;
}

function fn_newsman_update_user_profile_post($user_id, $user_data, $action)
{
	if ($action == "add")
	{
		$batchSize = 5000;

		$vars = Registry::get('addons.newsman');
		$userid = $vars['newsman_userid'];
		$apikey = $vars['newsman_apikey'];
		$listid = $vars['newsman_list'];
		$importType = $vars['newsman_importType'];


		$first = false;

		$time = cronTime("Select");
		if ($time->num_rows == 0)
		{
			cronTime("Insert", time());
			$first = true;
		}

		$time = cronTime("Select");

		foreach ($time as $_time)
		{
			$time = $_time["time"];
		}

		$timefromdatabase = $time;

		$dif = time() - $timefromdatabase;

		if (!$first)
		{
			if ($dif > 3600)
			{
				cronTime("Update", time());
			} else
			{
				die('cannot execute scripts, 1 hour must pass');
			}
		}


		if (empty($importType["importOrders"]) && empty($importType["importSubscribers"]))
		{
			fn_set_notification('W', 'Import Type', 'Please choose an import type (Subscribers or Customers)', 'S');
			return false;
		}

		if ($importType["importOrders"] != "Y" && $importType["importSubscribers"] != "Y")
		{
			fn_set_notification('W', 'Import Type', 'Please choose an import type (Subscribers or Customers)', 'S');
			return false;
		}

		if (!empty($userid) && !empty($apikey) && !empty($listid))
		{
			$client = new Newsman_Client($userid, $apikey);
			$client->setCallType("rest");

			if ($importType["importSubscribers"] == "Y")
			{
				//Subscribers
				$customers_to_import = array();

				$users = db_query('SELECT * FROM ?:em_subscribers WHERE status = ?i', "A");

				foreach ($users as $user)
				{
					$customers_to_import[] = array(
						"email" => $user["email"],
						"name" => (empty($user['name']) ? " " : $user['name'])
					);

					if ((count($customers_to_import) % $batchSize) == 0)
					{
						_importData($customers_to_import, $listid, null, $client, "cscart subscribers");
					}
				}
				if (count($customers_to_import) > 0)
				{
					_importData($customers_to_import, $listid, null, $client, "cscart subscribers");
				}

				unset($customers_to_import);
				//Subscribers
			}

			if ($importType["importOrders"] == "Y")
			{
				/*Orders Processing*/
				$customers_to_import = array();

				$orders = db_query('SELECT * FROM ?:orders WHERE status = ?i', "C");

				foreach ($orders as $order)
				{
					$customers_to_import[] = array(
						"email" => $order["email"],
						"name" => (empty($order['s_firstname']) ? " " : $order['s_firstname'])
					);

					if ((count($customers_to_import) % $batchSize) == 0)
					{
						_importData($customers_to_import, $listid, null, $client, "cscart orders_completed");
					}
				}
				if (count($customers_to_import) > 0)
				{
					_importData($customers_to_import, $listid, null, $client, "cscart orders_completed");
				}

				unset($customers_to_import);
				/*Orders Processing*/
			}
		}
	}
}


?>