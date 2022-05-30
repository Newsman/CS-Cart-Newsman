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

$_cHost = getenv('HTTP_HOST');
$currentHost = preg_replace('/^www\./', '', $_cHost);

foreach($_hosts as $h => $v)
{
	if($currentHost == $h)	
	{	
		if(empty($v))
			continue;

		$remarketingid = $v;
		$host = $h;
		break;
	}
}

Tygh::$app['view']->assign('newsmanRemarketingEnabled', (!empty($vars["newsman_remarketingenable"])) ? $vars['newsman_remarketingenable'] : "0");
Tygh::$app['view']->assign('newsmanRemarketingId', $_hosts[$host]);

if(!empty($vars["newsman_remarketingenable"]) && $vars['newsman_remarketingenable'] == "1")
{

$_REQUEST['category_id'] = empty($_REQUEST['category_id']) ? 0 : $_REQUEST['category_id'];

if ($mode == 'view') {

    Tygh::$app['view']->assign('newsmanMode', 'category');

    $params = $_REQUEST;

    if ($items_per_page = fn_change_session_param(Tygh::$app['session'], $_REQUEST, 'items_per_page')) {
        $params['items_per_page'] = $items_per_page;
    }
    if ($sort_by = fn_change_session_param(Tygh::$app['session'], $_REQUEST, 'sort_by')) {
        $params['sort_by'] = $sort_by;
    }
    if ($sort_order = fn_change_session_param(Tygh::$app['session'], $_REQUEST, 'sort_order')) {
        $params['sort_order'] = $sort_order;
    }

    $params['cid'] = $_REQUEST['category_id'];
    $params['extend'] = array('categories', 'description');
    $params['subcats'] = '';
    if (Registry::get('settings.General.show_products_from_subcategories') == 'Y') {
        $params['subcats'] = 'Y';
    }

    list($products, $search) = fn_get_products($params, Registry::get('settings.Appearance.products_per_page'), CART_LANGUAGE);

    $impressions = 'var prodData = [];';
    $impressions .= 'var prodDataName = [];';
    $addtocart = '';

    $int = 0;
    foreach($products as $prod){
        $int++;      

        $__category = db_query('SELECT * FROM ?:category_descriptions WHERE category_id = ?i', $_REQUEST['category_id']);

        foreach($__category as $___category){
            $__category = $___category["category"];
        }

        $name = str_replace("'", "", $prod["product"]);

        $impressions .= "
        _nzm.run( 'ec:addImpression', {
            'id': '" . $prod["product_id"] . "',
            'name': '" . $name . "',
            'category': '" . $__category . "',
            'list': 'Category List',
            'position': '" . $int . "'
        } );
        ";

        $impressions .= "
        //add to cart      

        prodData[" . $prod["product_id"] . "] = {
            'id': '" . $prod["product_id"] . "',
            'name': '" . $name . "',
            'price': '" . $prod["price"] . "',
            'quantity': 1
        };    
        
        prodDataName['" . $name . "'] = {
            'id': '" . $prod["product_id"] . "',
            'name': '" . $name . "',
            'price': '" . $prod["price"] . "',
            'quantity': 1
        }; 
     
        ";
    }    

  $return = "

        " . $impressions . "

        _nzm.run( 'send', 'pageview' );          

 ";
 
        Tygh::$app['view']->assign('newsmanModeCategory', $return);
    }
}