<?php

use Tygh\Registry;
use Tygh\BlockManager\ProductTabs;
use Tygh\Settings;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

$vars = Registry::get('addons.newsman');

if(!empty($vars["newsman_remarketing"]))
{

$_REQUEST['category_id'] = empty($_REQUEST['category_id']) ? 0 : $_REQUEST['category_id'];

if ($mode == 'view') {

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

        " . $impressions . "

        _nzm.run( 'send', 'pageview' );
        
        (function(){

            function _loadEvents(){

                if (window.jQuery) { 
                    
                    //add to cart
                    $( '.ty-btn__add-to-cart').each(function(index) {
                        $(this).on('click', function(){
                     
                            var id = $(this).attr('id');
                            id = id.replace('button_cart_', '');           
                         
                            _nzm.run('ec:addProduct', {
                                'id': prodData[id].id,
                                'name': prodData[id].name,                             
                                'price': prodData[id].price,
                                'quantity': 1
                            });
                            _nzm.run('ec:setAction', 'add');
                            _nzm.run('send', 'event', 'UX', 'click', 'add to cart');

                        });
                    }); 

                    //remove from cart                
                    $('.cm-ajax-full-render[data-ca-dispatch=\"delete_cart_item\"]').each(function () {
                        jQuery(this).bind('click', {'elem': jQuery(this)}, function (ev) {                                              

                            var _c = jQuery(this).closest('.ty-cart-items__list-item');        
                            _c = _c.find('.ty-cart-items__list-item-desc a');      
                            var _name = _c.html();                              
                            
                            var qty = _c.find('p:first span:first').html();
                            qty = 1;              
                            
                            _nzm.run('ec:addProduct', {
                                'id': prodDataName[_name].id,
                                'quantity': qty
                            });
            
                            _nzm.run('ec:setAction', 'remove');
                            _nzm.run('send', 'event', 'UX', 'click', 'remove from cart');                            
            
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