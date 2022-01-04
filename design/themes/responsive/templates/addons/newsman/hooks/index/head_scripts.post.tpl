{nocache}
{if $newsmanRemarketingEnabled eq '1'}

<script id='newsman_scripts'>      

        var _nzmPluginInfo = '1.0:CsCart';
        var _nzm = _nzm || [];
		var _nzm_config = _nzm_config || [];
		_nzm_config['disable_datalayer'] = 1;
		(function () {
    		var a, methods, i;
    		a = function (f) {
    			return function () {
    				_nzm.push([f].concat(Array.prototype.slice.call(arguments, 0)));
    			}
    		};
    		methods = ['identify', 'track', 'run'];
    		for (i = 0; i < methods.length; i++) {
    			_nzm[methods[i]] = a(methods[i])
    		}
    		;
    		s = document.getElementsByTagName('script')[0];
    		var script_dom = document.createElement('script');
    		script_dom.async = true;
    		script_dom.id = 'nzm-tracker';
    		script_dom.setAttribute('data-site-id', '{$newsmanRemarketingId}');
    		script_dom.src = 'https://retargeting.newsmanapp.com/js/retargeting/track.js';
    		s.parentNode.insertBefore(script_dom, s);
	    })();

		_nzm.run( 'require', 'ec' );
		_nzm.run( 'set', 'currencyCode', 'RON' );	     

		NewsmanAutoEvents();			
		setInterval(NewsmanAutoEvents, 5000);

		let lastCart = sessionStorage.getItem('lastCart');			
		if(lastCart === null)
			lastCart = {};			
		let lastCartFlag = false;
		
		function NewsmanAutoEvents(){	
				
			var ajaxurl = '/index.php?dispatch=newsman.view&newsman=getCart.json';
			
			jQuery.post(ajaxurl, {  
	           post: true,
            }, function (response) {	
							
				lastCart = JSON.parse(sessionStorage.getItem('lastCart'));						
				if(lastCart === null)
					lastCart = {};	

				//check cache
				if(lastCart.length > 0 && lastCart != null && lastCart != undefined && response.length > 0 && response != null && response != undefined)
				{				
					if(JSON.stringify(lastCart) === JSON.stringify(response))
					{
						console.log('newsman remarketing: cache loaded, cart is unchanged');
						lastCartFlag = true;					
					}
					else{
						lastCartFlag = false;
					}
				}			

				if(response.length > 0 && lastCartFlag == false)
				{
					_nzm.run('ec:setAction', 'clear_cart');
					_nzm.run('send', 'event', 'detail view', 'click', 'clearCart');	
					for (var item in response) {				
						_nzm.run( 'ec:addProduct', 
							response[item]
						);				
					}	
					
					_nzm.run( 'ec:setAction', 'add' );
					_nzm.run( 'send', 'event', 'UX', 'click', 'add to cart' );
					sessionStorage.setItem('lastCart', JSON.stringify(response));					
					console.log('newsman remarketing: cart sent');				
				}
				else{
					console.log('newsman remarketing: request not sent');
				}
				
            });

		}   
		
		{if $newsmanMode eq 'cart'}
		
            {$newsmanModeCart nofilter}
        
        {elseif $newsmanMode eq 'complete'}
        
           {$newsmanModeComplete nofilter}
            
        {elseif $newsmanMode eq 'checkout'}
        
           {$newsmanModeCheckout nofilter}         
           
        {elseif $newsmanMode eq 'index'}
        
           {$newsmanModeIndex nofilter}    

        {elseif $newsmanMode eq 'product'}
        
           {$newsmanModeProduct nofilter}      
           
        {elseif $newsmanMode eq 'category'}
        
           {$newsmanModeCategory nofilter}             
            
        {/if}
  
</script>

{/if}
{/nocache}