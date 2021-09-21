<?php

use Tygh\Registry;
use Tygh\BlockManager\ProductTabs;
use Tygh\Settings;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

$vars = Registry::get('addons.newsman');

$_h = array(
 $vars["newsman_remarketinglblone"],
 $vars["newsman_remarketinglbltwo"],
 $vars["newsman_remarketinglblthree"],
 $vars["newsman_remarketinglblfour"],
 $vars["newsman_remarketinglblfive"]
);

$_hosts = array(
(!empty($vars["newsman_remarketinglblone"]) ? $vars["newsman_remarketinglblone"] : null) => (!empty($vars["newsman_remarketingone"]) ? $vars["newsman_remarketingone"] : null),
(!empty($vars["newsman_remarketinglbltwo"]) ? $vars["newsman_remarketinglbltwo"] : null) => (!empty($vars["newsman_remarketingtwo"]) ? $vars["newsman_remarketingtwo"] : null),
(!empty($vars["newsman_remarketinglblthree"]) ? $vars["newsman_remarketinglblthree"] : null) => (!empty($vars["newsman_remarketingthree"]) ? $vars["newsman_remarketingthree"] : null),
(!empty($vars["newsman_remarketinglblfour"]) ? $vars["newsman_remarketinglblfour"] : null) => (!empty($vars["newsman_remarketingfour"]) ? $vars["newsman_remarketingfour"] : null),
(!empty($vars["newsman_remarketinglblfive"]) ? $vars["newsman_remarketinglblfive"] : null) => (!empty($vars["newsman_remarketingfive"]) ? $vars["newsman_remarketingfive"] : null)
);

$remarketingid = "";
$host = "";

foreach($_hosts as $h => $v)
{
	if(getenv('HTTP_HOST') == $h)
	{	
		if(empty($v))
			continue;

		$remarketingid = $v;
		$host = $h;
		break;
	}
}

Tygh::$app['view']->assign('newsmanRemarketingEnabled', (!empty($vars["newsman_remarketingenable"])) ? $vars['newsman_remarketingenable'] : "0");
Tygh::$app['view']->assign('newsmanRemarketingId', $_hosts[$h]);

if(!empty($vars["newsman_remarketingenable"]) && $vars['newsman_remarketingenable'] == "1")
{
    
  Tygh::$app['view']->assign('newsmanMode', 'index');

  $return = "

            function _loadEvents(){

            }

            _loadEvents();
            
 ";
 
      Tygh::$app['view']->assign('newsmanModeIndex', $return);
   
}