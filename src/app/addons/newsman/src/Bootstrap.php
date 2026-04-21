<?php

namespace Tygh\Addons\Newsman;

use Tygh\Core\ApplicationInterface;
use Tygh\Core\BootstrapInterface;
use Tygh\Core\HookHandlerProviderInterface;

class Bootstrap implements BootstrapInterface, HookHandlerProviderInterface
{
    public function __construct()
    {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
    }

    /**
     * @param ApplicationInterface $app
     */
    public function boot(ApplicationInterface $app)
    {
        $app->register(new ServiceProvider());
    }

    /**
     * @return array
     */
    public function getHookHandlerMap()
    {
        return array(
            // Subscribe new customers to Newsman when they register
            'update_profile' => array(
                'addons.newsman.hook_handlers.profile',
                'onUpdateProfile',
            ),
            // Subscribe customer to Newsman when an order is placed
            'place_order' => array(
                'addons.newsman.hook_handlers.order',
                'onPlaceOrder',
            ),
            // Sync subscriber status when an order status changes
            'change_order_status_post' => array(
                'addons.newsman.hook_handlers.order',
                'onChangeOrderStatusPost',
            ),
            // Sync subscribe/unsubscribe from the built-in newsletter module
            'newsletters_update_subscriptions_post' => array(
                'addons.newsman.hook_handlers.newsletter',
                'onNewsletterUpdateSubscriptions',
            ),
            // Inject remarketing tracking scripts on storefront pages
            'dispatch_before_display' => array(
                'addons.newsman.hook_handlers.remarketing',
                'onDispatchBeforeDisplay',
            ),
        );
    }
}
