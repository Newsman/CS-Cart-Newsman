<?php

use Tygh\Registry;
use Tygh\BlockManager\ProductTabs;
use Tygh\Settings;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

$vars = Registry::get('addons.newsman');

if(!empty($vars["newsman_remarketing"]))
{

if ($mode == 'complete') {

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

    $return = "
<div id='newsman_scripts'>
    <script>            

        var _nzm = _nzm || [];
		var _nzm_config = _nzm_config || [];
		(function() {
			if (!_nzm.track) {
                _nzm_config['disable_datalayer'] = 1;
				var a, methods, i;
				a = function(f) {
					return function() {
						_nzm.push([f].concat(Array.prototype.slice.call(arguments, 0)));
					}
				};
				methods = ['identify', 'track', 'run'];
				for(i = 0; i < methods.length; i++) {
					_nzm[methods[i]] = a(methods[i])
				};
				s = document.getElementsByTagName('script')[0];
				var script_dom = document.createElement('script');
				script_dom.async = true;
				script_dom.id    = 'nzm-tracker';
				script_dom.setAttribute('data-site-id', '" . $vars['newsman_remarketing'] . "');
				script_dom.src = 'https://retargeting.newsmanapp.com/js/retargeting/track.js';
				s.parentNode.insertBefore(script_dom, s);
			}
		})();

		_nzm.run( 'require', 'ec' );
		_nzm.run( 'set', 'currencyCode', 'RON' );	        
        
        (function(){

            function _loadEvents(){

                if (window.jQuery) { 

                    //purchase
                    _nzm.identify({ email: '" . $order_info["email"] . "', first_name: '" . $order_info["firstname"] . "', last_name: '" . $order_info["lastname"] . "' });    
                    
                   ";

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

                $return .= "_nzm.run('ec:setAction', 'purchase',{
                        'id': '" . $order_info["order_id"] . "',
                        'affiliation': '',
                        'revenue': '" . $order_info["total"] . "',
                        'tax': '0',
                        'shipping': '" . $order_info["shipping_cost"] . "'
                    });
                    _nzm.run('send', 'pageview');

                    jQuery('#newsman_scripts').appendTo('body');

                }
                else{
                    setTimeout(function(){

                        _loadEvents();

                    }, 1000);
                }

            }

            if(!window.jQuery){
                _loadEvents();
            }

        })();
    
    </script>   
</div>    
 ";

 echo $return;
    }

    if ($mode == 'cart') {

  echo "
<div id='newsman_scripts'>
    <script>            

        var _nzm = _nzm || [];
		var _nzm_config = _nzm_config || [];
		(function() {
			if (!_nzm.track) {
				var a, methods, i;
				a = function(f) {
					return function() {
						_nzm.push([f].concat(Array.prototype.slice.call(arguments, 0)));
					}
				};
				methods = ['identify', 'track', 'run'];
				for(i = 0; i < methods.length; i++) {
					_nzm[methods[i]] = a(methods[i])
				};
				s = document.getElementsByTagName('script')[0];
				var script_dom = document.createElement('script');
				script_dom.async = true;
				script_dom.id    = 'nzm-tracker';
				script_dom.setAttribute('data-site-id', '" . $vars['newsman_remarketing'] . "');
				script_dom.src = 'https://retargeting.newsmanapp.com/js/retargeting/track.js';
				s.parentNode.insertBefore(script_dom, s);
			}
		})();

		_nzm.run( 'require', 'ec' );
		_nzm.run( 'set', 'currencyCode', 'RON' );	        
        
        (function(){

            function _loadEvents(){

                if (window.jQuery) { 

                    //remove from cart                
                    $('.ty-cart-content__product-delete').each(function () {
                        jQuery(this).bind('click', {'elem': jQuery(this)}, function (ev) {                                              

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
            
                            alert('');

                        });
                    });                                         

                    jQuery('#newsman_scripts').appendTo('body');

                }
                else{
                    setTimeout(function(){

                        _loadEvents();

                    }, 1000);
                }

            }

            if(!window.jQuery){
                _loadEvents();
            }

        })();
    
    </script>   
</div>    
 ";
    }

}