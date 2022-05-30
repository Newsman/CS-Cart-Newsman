{nocache}
{if $newsmanRemarketingEnabled eq '1'}

<script id='newsman_scripts'>      

//Newsman remarketing tracking code  

var endpoint = 'https://retargeting.newsmanapp.com';
var remarketingEndpoint = endpoint + '/js/retargeting/track.js';
var remarketingid = '{$newsmanRemarketingId}';

var _nzmPluginInfo = '1.1:cscart';
var _nzm = _nzm || [];
var _nzm_config = _nzm_config || [];
_nzm_config['disable_datalayer'] = 1;
_nzm_tracking_server = endpoint;
(function() {
    var a, methods, i;
    a = function(f) {
        return function() {
            _nzm.push([f].concat(Array.prototype.slice.call(arguments, 0)));
        }
    };
    methods = ['identify', 'track', 'run'];
    for (i = 0; i < methods.length; i++) {
        _nzm[methods[i]] = a(methods[i])
    };
    s = document.getElementsByTagName('script')[0];
    var script_dom = document.createElement('script');
    script_dom.async = true;
    script_dom.id = 'nzm-tracker';
    script_dom.setAttribute('data-site-id', remarketingid);
    script_dom.src = remarketingEndpoint;
    s.parentNode.insertBefore(script_dom, s);
})();
_nzm.run('require', 'ec');

//Newsman remarketing tracking code     

//Newsman remarketing auto events

var isProd = true;

let lastCart = sessionStorage.getItem('lastCart');
if (lastCart === null)
    lastCart = {};

var lastCartFlag = false;
var firstLoad = true;
var bufferedXHR = false;
var unlockClearCart = true;
var ajaxurl = '/index.php?dispatch=newsman.view&newsman=getCart.json';
var documentComparer = document.location.origin;
var documentUrl = document.URL;
var sameOrigin = (documentUrl.indexOf(documentComparer) !== -1);

let startTime, endTime;

function startTimePassed() {
    startTime = new Date();
};

startTimePassed();

function endTimePassed() {
    var flag = false;

    endTime = new Date();
    var timeDiff = endTime - startTime;

    timeDiff /= 1000;

    var seconds = Math.round(timeDiff);

    if (firstLoad)
        flag = true;

    if (seconds >= 5)
        flag = true;

    return flag;
}

if (sameOrigin) {
    NewsmanAutoEvents();
    setInterval(NewsmanAutoEvents, 5000);

    detectXHR();
}

function NewsmanAutoEvents() {

    if (!endTimePassed())
        return;

    let xhr = new XMLHttpRequest()

    if (bufferedXHR || firstLoad) {

        xhr.open('GET', ajaxurl, true);

        startTimePassed();

        xhr.onload = function() {

            if (xhr.status == 200 || xhr.status == 201) {

                var response = JSON.parse(xhr.responseText);

                lastCart = JSON.parse(sessionStorage.getItem('lastCart'));

                if (lastCart === null)
                    lastCart = {};

                //check cache
                if (lastCart.length > 0 && lastCart != null && lastCart != undefined && response.length > 0 && response != null && response != undefined) {
                    if (JSON.stringify(lastCart) === JSON.stringify(response)) {
                        if (!isProd)
                            console.log('newsman remarketing: cache loaded, cart is unchanged');

                        lastCartFlag = true;
                    } else {
                        lastCartFlag = false;

                        if (!isProd)
                            console.log('newsman remarketing: cache loaded, cart is changed');
                    }
                }

                if (response.length > 0 && lastCartFlag == false) {

                    addToCart(response);

                }
                //send only when on last request, products existed
                else if (response.length == 0 && lastCart.length > 0 && unlockClearCart) {

                    clearCart();

                    if (!isProd)
                        console.log('newsman remarketing: clear cart sent');

                } else {

                    if (!isProd)
                        console.log('newsman remarketing: request not sent');

                }

                firstLoad = false;
                bufferedXHR = false;

            }

        }

        xhr.send(null);

    } else {
        if (!isProd)
            console.log('newsman remarketing: !buffered xhr || first load');
    }

}

function clearCart() {

    _nzm.run('ec:setAction', 'clear_cart');
    _nzm.run('send', 'event', 'detail view', 'click', 'clearCart');

    sessionStorage.setItem('lastCart', JSON.stringify([]));

    unlockClearCart = false;

}

function addToCart(response) {

    _nzm.run('ec:setAction', 'clear_cart');
    _nzm.run('send', 'event', 'detail view', 'click', 'clearCart', null, _nzm.createFunctionWithTimeout(function() {

        for (var item in response) {

            _nzm.run('ec:addProduct',
                response[item]
            );

        }

        _nzm.run('ec:setAction', 'add');
        _nzm.run('send', 'event', 'UX', 'click', 'add to cart');

        sessionStorage.setItem('lastCart', JSON.stringify(response));
        unlockClearCart = true;

        if (!isProd)
            console.log('newsman remarketing: cart sent');

    }));

}

function detectXHR() {

    var proxied = window.XMLHttpRequest.prototype.send;
    window.XMLHttpRequest.prototype.send = function() {

        var pointer = this;
        var validate = false;
        var intervalId = window.setInterval(function() {

            if (pointer.readyState != 4) {
                return;
            }

            var _location = pointer.responseURL;

            //own request exclusion
            if (
				pointer.responseURL.indexOf('getCart.json') >= 0 ||
				//magento 2-2.3.x
				pointer.responseURL.indexOf('/static/') >= 0 ||
				pointer.responseURL.indexOf('/pub/static') >= 0 ||
				pointer.responseURL.indexOf('/customer/section') >= 0 ||
				//opencart 1
				pointer.responseURL.indexOf('getCart=true') >= 0
            ) {
                validate = false;
            } else {
                if (_location.indexOf(window.location.origin) !== -1)
                    validate = true;
            }

            if (validate) {
                bufferedXHR = true;

                if (!isProd)
                    console.log('newsman remarketing: ajax request fired and catched from same domain');

                NewsmanAutoEvents();
            }

            clearInterval(intervalId);

        }, 1);

        return proxied.apply(this, [].slice.call(arguments));
    };

}

//Newsman remarketing auto events

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