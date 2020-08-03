<?php

use Tygh\Registry;
use Tygh\BlockManager\ProductTabs;
use Tygh\Settings;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

$vars = Registry::get('addons.newsman');

if(!empty($vars["newsman_remarketing"]))
{

if ($mode == 'view') {

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

  echo "
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

 
        _nzm.run( 'ec:addProduct', {
            'id': '" . $product["main_pair"]["detailed"]["object_id"] . "', // Product ID (string)
            'name': '" . $product["product"] . "', // Product name (string)
            'category': '" . $product["name"] . "', // Product category (string)
            'price': '" . $product["price"] . "', // Product price
        } );
        _nzm.run( 'ec:setAction', 'detail' );

        _nzm.run( 'send', 'pageview' );

        
        (function(){

            function _loadEvents(){

                if (window.jQuery) { 

                    jQuery('.ty-btn__add-to-cart').click(function(){

                        _nzm.run('ec:addProduct', {
                            'id': '" . $product["main_pair"]["detailed"]["object_id"] . "',
                            'name': '" . $product["product"] . "',
                            'category': '',
                            'price': '" . $product["price"] . "',
                            'quantity': jQuery('.ty-value-changer__input').val()
                        });
                        _nzm.run('ec:setAction', 'add');
                        _nzm.run('send', 'event', 'UX', 'click', 'add to cart');
            
                    });

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
 ";
    }
}