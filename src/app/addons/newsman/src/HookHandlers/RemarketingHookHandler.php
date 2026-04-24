<?php

namespace Tygh\Addons\Newsman\HookHandlers;

use Pimple\Container;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Remarketing\Renderer;
use Tygh\Registry;
use Tygh\Tygh;

class RemarketingHookHandler
{
    /** @var Container */
    protected $app;

    /** @var Config */
    protected $config;

    /** @var Renderer */
    protected $renderer;

    /** @var Logger */
    protected $logger;

    public function __construct(Container $app, Config $config, Renderer $renderer, Logger $logger)
    {
        $this->app = $app;
        $this->config = $config;
        $this->renderer = $renderer;
        $this->logger = $logger;
    }

    /**
     * Hook: dispatch_before_display
     *
     * Fires after controllers have run and the view has been populated,
     * so we can reuse view variables like `products` and `category_data`
     * without issuing extra SQL queries.
     */
    public function onDispatchBeforeDisplay()
    {
        if (AREA !== 'C') {
            return;
        }

        if (!$this->config->isRemarketingActive()) {
            return;
        }

        $controller = (string) Registry::get('runtime.controller');
        $mode = (string) Registry::get('runtime.mode');
        $dispatch = $controller . '.' . $mode;

        try {
            /** @var \Tygh\SmartyEngine\Core $view */
            $view = $this->app['view'];

            // Currency the shopper is viewing prices in (falls back to primary)
            $currencyCode = defined('CART_SECONDARY_CURRENCY') ? CART_SECONDARY_CURRENCY
                : (defined('CART_PRIMARY_CURRENCY') ? CART_PRIMARY_CURRENCY : '');

            // Tracking script for <head>
            $view->assign('newsman_tracking_script', $this->renderer->renderTrackingScript($currencyCode));

            // Body scripts
            $bodyScripts = '';
            $isCheckoutComplete = ($dispatch === 'checkout.complete');

            // Cart tracking
            $cartUrl = fn_url('newsman_front.cart', 'C');
            // Scope the nzm_cart_sync session cookie to the current storefront's
            // base URL path so sibling storefronts on the same domain (rare on
            // CS-Cart multistore, but supported) do not share one session flag.
            $cookiePath = parse_url((string) Registry::get('config.current_location'), PHP_URL_PATH);
            if ($cookiePath === null || $cookiePath === false || $cookiePath === '' || $cookiePath[0] !== '/') {
                $cookiePath = '/';
            }
            $bodyScripts .= $this->renderer->renderCartTracking($cartUrl, $isCheckoutComplete, $cookiePath);

            // Customer identify (not on checkout complete -- purchase handles it).
            // When Varnish / full_page_cache is enabled the page HTML is shared
            // across users, so inline per-user data is unsafe. Fall back to a
            // static bootstrap that fetches the user via a non-cacheable endpoint.
            if (!$isCheckoutComplete) {
                if ($this->config->isFullPageCacheActive()) {
                    $identifyUrl = fn_url('newsman_front.identify', 'C');
                    $bodyScripts .= $this->renderer->renderCustomerIdentifyDeferred($identifyUrl);
                } else {
                    $auth = isset(Tygh::$app['session']['auth']) ? Tygh::$app['session']['auth'] : array();
                    if (!empty($auth['user_id'])) {
                        $userInfo = fn_get_user_info($auth['user_id']);
                        if (!empty($userInfo['email'])) {
                            $bodyScripts .= $this->renderer->renderCustomerIdentify(
                                $userInfo['email'],
                                isset($userInfo['firstname']) ? $userInfo['firstname'] : '',
                                isset($userInfo['lastname']) ? $userInfo['lastname'] : ''
                            );
                        }
                    }
                }
            }

            // Page-type specific tracking
            if ($dispatch === 'products.view' && !empty($_REQUEST['product_id'])) {
                $productId = (int) $_REQUEST['product_id'];
                $product = $view->getTemplateVars('product');
                if (!is_array($product)) {
                    $product = array();
                }
                $categoryName = '';
                if (!empty($product['main_category'])) {
                    $categoryName = $this->getCategoryName((int) $product['main_category']);
                }
                $bodyScripts .= $this->renderer->renderProductView(
                    $productId,
                    isset($product['product']) ? $product['product'] : '',
                    $categoryName,
                    isset($product['price']) ? $product['price'] : ''
                );
                $bodyScripts .= $this->renderer->renderPageView();
            } elseif ($dispatch === 'categories.view' && !empty($_REQUEST['category_id'])) {
                $categoryId = (int) $_REQUEST['category_id'];
                $products = $view->getTemplateVars('products');
                if (!is_array($products)) {
                    $products = array();
                }
                $categoryData = $view->getTemplateVars('category_data');
                $categoryName = is_array($categoryData) && !empty($categoryData['category']) ? $categoryData['category'] : '';
                $bodyScripts .= $this->renderer->renderCategoryView($categoryId, $products, $categoryName);
                $bodyScripts .= $this->renderer->renderPageView();
            } elseif ($isCheckoutComplete && !empty($_REQUEST['order_id'])) {
                $orderInfo = fn_get_order_info($_REQUEST['order_id']);
                if (!empty($orderInfo)) {
                    $bodyScripts .= $this->renderer->renderPurchase($orderInfo);
                }
            } else {
                $bodyScripts .= $this->renderer->renderPageView();
            }

            $view->assign('newsman_body_scripts', $bodyScripts);
        } catch (\Exception $e) {
            $this->logger->logException($e);
        }
    }

    /**
     * @param int $categoryId
     * @return string
     */
    public function getCategoryName($categoryId)
    {
        return (string) db_get_field(
            "SELECT category FROM ?:category_descriptions WHERE category_id = ?i AND lang_code = ?s",
            $categoryId,
            CART_LANGUAGE
        );
    }

}
