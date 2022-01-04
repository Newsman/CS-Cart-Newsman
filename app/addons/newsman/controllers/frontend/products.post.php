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

if ($mode == 'view') {
    
     Tygh::$app['view']->assign('newsmanMode', 'product');

    $product = fn_get_product_data(
        $_REQUEST['product_id'],
        $auth,
        CART_LANGUAGE,
        '',
        true,
        true,
        true,
        true,
        fn_is_preview_action($auth, $_REQUEST),
        true,
        false,
        true
    );

  $return = "

        _nzm.run( 'ec:addProduct', {
            'id': '" . $product["main_pair"]["detailed"]["object_id"] . "', // Product ID (string)
            'name': '" . $product["product"] . "', // Product name (string)
            'category': '" . $product["name"] . "', // Product category (string)
            'price': '" . $product["price"] . "', // Product price
        } );
        _nzm.run( 'ec:setAction', 'detail' );

        _nzm.run( 'send', 'pageview' );

            function _loadEvents(){

                function addToCart()
                {
                    //add to cart
                    //.ty_btn__add-to-cart
                    
                    var _class = '';
                    var validate = jQuery('.ty-product-block__button button.ty-btn').text();
                    if(validate != '')
                    {
                        _class = '.ty-product-block__button button.ty-btn';
                    }
                
                    if(validate == '')
                    {
                        validate = jQuery('.product-info button.ty-btn').text();

                        if(validate != '')
                        {
                            _class = '.product-info button.ty-btn';
                        }
                    }

                    jQuery(_class).on('click', function(){

                        _nzm.run('ec:addProduct', {
                            'id': '" . $product["main_pair"]["detailed"]["object_id"] . "',
                            'name': '" . $product["product"] . "',
                            'category': '',
                            'price': '" . $product["price"] . "',
                            'quantity': jQuery('.ty-value-changer__input').val()
                        });
                        _nzm.run('ec:setAction', 'add');
                        _nzm.run('send', 'event', 'UX', 'click', 'add to cart');
            
                        setTimeout(function() {

                          bindRemoveFromCart();

                        }, 2000);

                    });
                }
                
                addToCart();
                   
                                   
                function nzSelectChange()
                {
                    $('.ty-product-options select').change(function() {
                        
                        setTimeout(function()
                        {
                            nzSelectChange();
                            addToCart();   
                        }, 1000);
                        
                    });
                }
                
                nzSelectChange();

                    function bindRemoveFromCart()
                    {
                        //unbind
                        $('.cm-ajax-full-render[data-ca-dispatch=\"delete_cart_item\"]').each(function () {
                            jQuery(this).unbind('click');
                        });                          

                        //bind
                        $('.cm-ajax-full-render[data-ca-dispatch=\"delete_cart_item\"]').each(function () {
                            jQuery(this).bind('click', {'elem': jQuery(this)}, function (ev) {                                              

                                var _c = jQuery(this).parent().find('.ty-cart-items__list-item-desc');                                                              

                                var id = jQuery(this).attr('href');                   
                                var id = id.substring(id.indexOf('product_id%3D') + 13);                  
                                
                                var qty = _c.find('p:first span:first').html();
                                qty = 1;              
                                
                                _nzm.run('ec:addProduct', {
                                    'id': id,
                                    'quantity': qty
                                });
                
                                _nzm.run('ec:setAction', 'remove');
                                _nzm.run('send', 'event', 'UX', 'click', 'remove from cart');                            
                
                            });
                        });  
                    }       
                    
                    bindRemoveFromCart();

            }

            //_loadEvents();
    
 ";
    
      Tygh::$app['view']->assign('newsmanModeProduct', $return);
 
    }
}