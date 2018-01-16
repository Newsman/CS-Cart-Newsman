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
 * PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
 * "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
 ****************************************************************************/

if (!defined('BOOTSTRAP'))
{
	die('Access denied');
}

use Tygh\Registry;
use Tygh\Settings;

require_once(realpath(dirname(__FILE__)) . '/lib/Newsman/Client.php');

function fn_settings_variants_addons_newsman_newsman_list()
{
	try
	{
		$vars = Registry::get('addons.newsman');
		$userid = $vars['newsman_userid'];
		$apikey = $vars['newsman_apikey'];
		$listid = $vars['newsman_list'];

		if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			if (empty($userid) || empty($apikey))
			{
				fn_set_notification('W', 'Check fields', 'User Id and Api Key cannot be empty', 'S');
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
			$vars = Registry::get('addons.newsman');
			$userid = $vars['newsman_userid'];
			$apikey = $vars['newsman_apikey'];
			$listid = $vars['newsman_list'];

			$client = new Newsman_Client($userid, $apikey);

			$users = db_query('SELECT * FROM ?:em_subscribers WHERE status = ?i', "A");

			$emails = array();
			$name = array();

			foreach ($users as $user)
			{
				$emails[] = $user['email'];
				$name[] = (empty($user['name']) ? " " : $user['name']);
			}

			$max = 9999;

			$csv = "email,name" . "\n";
			for ($int = 0; $int < count($emails); $int++)
			{
				$csv .= $emails[$int];
				$csv .= ",";
				$csv .= $name[$int];
				$csv .= "\n";

				if ($int == $max)
				{
					$int = 0;

					$ret = $client->import->csv($listid, array(), $csv);
				}
			}

			$ret = $client->import->csv($listid, array(), $csv);


			/*Orders Processing*/

			$orders = db_query('SELECT * FROM ?:orders WHERE status = ?i', "C");

			$emails = array();
			$name = array();

			foreach ($orders as $order)
			{
				$emails[] = $order['email'];
				$name[] = (empty($order['s_firstname']) ? " " : $order['s_firstname']);
			}

			$max = 9999;

			$csv = "email,name" . "\n";
			for ($int = 0; $int < count($emails); $int++)
			{
				$csv .= $emails[$int];
				$csv .= ",";
				$csv .= $name[$int];
				$csv .= "\n";

				if ($int == $max)
				{
					$int = 0;

					$ret = $client->import->csv($listid, array(), $csv);
				}
			}

			$ret = $client->import->csv($listid, array(), $csv);

			fn_set_notification('S', 'Import', 'Import has been executed successfully', 'S');
		}
	} catch (Exception $ex)
	{
		fn_set_notification('W', 'Credentials', 'User Id and Api Key are invalid', 'S');
		return false;
	}

	return $all_datas;
}

function fn_newsman_update_user_profile_post($user_id, $user_data, $action)
{
	if ($action == "add")
	{
		$vars = Registry::get('addons.newsman');
		$userid = $vars['newsman_userid'];
		$apikey = $vars['newsman_apikey'];
		$listid = $vars['newsman_list'];

		if (!empty($userid) && !empty($apikey) && !empty($listid))
		{
			$client = new Newsman_Client($userid, $apikey);
			$client->setCallType("rest");

			$users = db_query('SELECT * FROM ?:em_subscribers WHERE status = ?i', "A");

			$emails = array();
			$name = array();

			foreach ($users as $user)
			{
				$emails[] = $user['email'];
				$name[] = (empty($user['name']) ? " " : $user['name']);
			}

			$max = 9999;

			$csv = "email,name" . "\n";
			for ($int = 0; $int < count($emails); $int++)
			{
				$csv .= $emails[$int];
				$csv .= ",";
				$csv .= $name[$int];
				$csv .= "\n";

				if ($int == $max)
				{
					$int = 0;

					$ret = $client->import->csv($listid, array(), $csv);
				}
			}
			$ret = $client->import->csv($listid, array(), $csv);


			/*Orders Processing*/

			$orders = db_query('SELECT * FROM ?:orders WHERE status = ?i', "C");

			$emails = array();
			$name = array();

			foreach ($orders as $order)
			{
				$emails[] = $order['email'];
				$name[] = (empty($order['s_firstname']) ? " " : $order['s_firstname']);
			}

			$max = 9999;

			$csv = "email,name" . "\n";
			for ($int = 0; $int < count($emails); $int++)
			{
				$csv .= $emails[$int];
				$csv .= ",";
				$csv .= $name[$int];
				$csv .= "\n";

				if ($int == $max)
				{
					$int = 0;

					$ret = $client->import->csv($listid, array(), $csv);
				}
			}

			$ret = $client->import->csv($listid, array(), $csv);
		}
	}
}

?>