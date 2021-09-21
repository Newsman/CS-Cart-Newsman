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

if ($mode == 'complete') {

    Tygh::$app['view']->assign('newsmanMode', 'complete');

    $order_info = fn_get_order_info($_REQUEST['order_id']);

    if (!empty($order_info['is_parent_order']) && $order_info['is_parent_order'] == 'Y') {
        $child_ids = db_get_fields(
            "SELECT order_id FROM ?:orders WHERE parent_order_id = ?i", $_REQUEST['order_id']
        );
        $order_info['child_ids'] = implode(',', $child_ids);
    }
    if (!empty($order_info)) {
        Tygh::$app['view']->assign('order_info', $order_info);
    }

    $_products = $order_info["products"];

    $return = '

            function _loadEvents(){

                _nzm.identify({ email: "' . $order_info["email"] . '", first_name: "' . $order_info["firstname"] . '", last_name: "' . $order_info["lastname"] . '" });
                
               ';

                foreach($_products as $_product)
                {         
                    $return .= "
                    _nzm.run( 'ec:addProduct', {
                        'id': '" . $_product["product_id"] . "',
                        'name': '" . $_product["product"] . "',
                        'category': '',
                        'price': '" . $_product["price"] . "',
                        'quantity': '" . $_product["amount"] . "'
                    } );                    
                    ";
                }

                $return .= '_nzm.run("ec:setAction", "purchase",{
                        "id": "' . $order_info["order_id"] . '",
                        "affiliation": "",
                        "revenue": "' . $order_info["total"] . '",
                        "tax": "0",
                        "shipping": "' . $order_info["shipping_cost"] . '"
                    });
                    _nzm.run("send", "pageview");

            }

           
            _loadEvents();

 ';

    Tygh::$app['view']->assign('newsmanModeComplete', $return);
 
}

if ($mode == 'cart') {
    
     Tygh::$app['view']->assign('newsmanMode', 'cart');
    
        $return = "

            function _loadEvents(){

                //remove from cart                
                $('.ty-cart-content__product-delete').each(function () {
                    jQuery(this).bind('click', function (ev) {                                              
                        var _c = jQuery(this).closest('tr');                        

                        _qty = _c.find('.ty-value-changer input');                      
                        _qty = _qty.val();

                        _c = _c.find('.quantity input');      
                        var _id = _c.val();                                                  
               
                        _nzm.run('ec:addProduct', {
                            'id': _id,
                            'quantity': _qty
                        });
        
                        _nzm.run('ec:setAction', 'remove');
                        _nzm.run('send', 'event', 'UX', 'click', 'remove from cart');        

                    });
                });             

            }
           
            _loadEvents();

 ";
 
     Tygh::$app['view']->assign('newsmanModeCart', $return);
    
}

if ($mode == 'checkout') {

    Tygh::$app['view']->assign('newsmanMode', 'checkout');

    $return = "
    
            function _loadEvents(){

            }

            _loadEvents();
        
     ";
     
    Tygh::$app['view']->assign('newsmanModeCheckout', $return);

}

}