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

                    //purchase
                    _nzm.identify({ email: '" . $order_info["email"] . "', first_name: '" . $order_info["firstname"] . "', last_name: '" . $order_info["lastname"] . "' });                           

                    _nzm.run('ec:setAction', 'purchase',{
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
    }
}