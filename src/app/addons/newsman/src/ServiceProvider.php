<?php

namespace Tygh\Addons\Newsman;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Tygh\Addons\Newsman\Action\Order\Save as OrderSave;
use Tygh\Addons\Newsman\Action\Order\Status as OrderStatus;
use Tygh\Addons\Newsman\Action\Subscribe\Email as SubscribeEmail;
use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Export\Authenticator;
use Tygh\Addons\Newsman\Export\PayloadParser;
use Tygh\Addons\Newsman\Export\Processor;
use Tygh\Addons\Newsman\Export\Renderer as ExportRenderer;
use Tygh\Addons\Newsman\Export\Router;
use Tygh\Addons\Newsman\HookHandlers\NewsletterHookHandler;
use Tygh\Addons\Newsman\HookHandlers\OrderHookHandler;
use Tygh\Addons\Newsman\HookHandlers\ProfileHookHandler;
use Tygh\Addons\Newsman\HookHandlers\RemarketingHookHandler;
use Tygh\Addons\Newsman\Remarketing\CartTracking;
use Tygh\Addons\Newsman\Remarketing\CartTrackingNative;
use Tygh\Addons\Newsman\Remarketing\CategoryView;
use Tygh\Addons\Newsman\Remarketing\CustomerIdentify;
use Tygh\Addons\Newsman\Remarketing\CustomerIdentifyDeferred;
use Tygh\Addons\Newsman\Remarketing\PageView;
use Tygh\Addons\Newsman\Remarketing\ProductView;
use Tygh\Addons\Newsman\Remarketing\Purchase;
use Tygh\Addons\Newsman\Remarketing\Renderer as RemarketingRenderer;
use Tygh\Addons\Newsman\Remarketing\TrackingScript;
use Tygh\Addons\Newsman\User\HostIpAddress;
use Tygh\Addons\Newsman\User\IpAddress;
use Tygh\Addons\Newsman\User\RemoteAddress;
use Tygh\Addons\Newsman\Util\ServerIpResolver;

class ServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $app
     */
    public function register(Container $app)
    {
        $app['addons.newsman.config'] = static function () {
            return new Config();
        };

        $app['addons.newsman.logger'] = static function ($app) {
            return new Logger($app['addons.newsman.config']);
        };

        $app['addons.newsman.api.client'] = static function ($app) {
            return new Client(
                $app['addons.newsman.config'],
                $app['addons.newsman.logger']
            );
        };

        // User / IP resolution
        $app['addons.newsman.user.remote_address'] = static function () {
            return new RemoteAddress(array(
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_REAL_IP',
                'HTTP_CF_CONNECTING_IP',
            ));
        };

        $app['addons.newsman.util.server_ip_resolver'] = static function () {
            return new ServerIpResolver();
        };

        $app['addons.newsman.user.host_ip'] = static function ($app) {
            return new HostIpAddress(
                $app['addons.newsman.config'],
                $app['addons.newsman.util.server_ip_resolver']
            );
        };

        $app['addons.newsman.user.ip_address'] = static function ($app) {
            return new IpAddress(
                $app['addons.newsman.config'],
                $app['addons.newsman.user.host_ip'],
                $app['addons.newsman.user.remote_address']
            );
        };

        // Services
        $app['addons.newsman.service.subscribe'] = static function ($app) {
            return new Service\SubscribeEmail($app['addons.newsman.api.client'], $app['addons.newsman.config'], $app['addons.newsman.logger']);
        };

        $app['addons.newsman.service.init_subscribe'] = static function ($app) {
            return new Service\InitSubscribeEmail($app['addons.newsman.api.client'], $app['addons.newsman.config'], $app['addons.newsman.logger']);
        };

        $app['addons.newsman.service.unsubscribe'] = static function ($app) {
            return new Service\UnsubscribeEmail($app['addons.newsman.api.client'], $app['addons.newsman.config'], $app['addons.newsman.logger']);
        };

        $app['addons.newsman.service.segment_add'] = static function ($app) {
            return new Service\SegmentAddSubscriber($app['addons.newsman.api.client'], $app['addons.newsman.config'], $app['addons.newsman.logger']);
        };

        $app['addons.newsman.service.save_order'] = static function ($app) {
            return new Service\SaveOrder($app['addons.newsman.api.client'], $app['addons.newsman.config'], $app['addons.newsman.logger']);
        };

        $app['addons.newsman.service.set_purchase_status'] = static function ($app) {
            return new Service\SetPurchaseStatus($app['addons.newsman.api.client'], $app['addons.newsman.config'], $app['addons.newsman.logger']);
        };

        $app['addons.newsman.service.get_remarketing_settings'] = static function ($app) {
            return new Service\GetRemarketingSettings($app['addons.newsman.api.client'], $app['addons.newsman.config'], $app['addons.newsman.logger']);
        };

        $app['addons.newsman.service.get_list_all'] = static function ($app) {
            return new Service\GetListAll($app['addons.newsman.api.client'], $app['addons.newsman.config'], $app['addons.newsman.logger']);
        };

        $app['addons.newsman.service.get_segment_all'] = static function ($app) {
            return new Service\GetSegmentAll($app['addons.newsman.api.client'], $app['addons.newsman.config'], $app['addons.newsman.logger']);
        };

        $app['addons.newsman.service.save_integration'] = static function ($app) {
            return new Service\SaveListIntegrationSetup(
                $app['addons.newsman.api.client'],
                $app['addons.newsman.config'],
                $app['addons.newsman.logger'],
                $app['addons.newsman.util.server_ip_resolver']
            );
        };

        $app['addons.newsman.service.configuration.integration'] = static function ($app) {
            return new Service\Configuration\Integration(
                $app['addons.newsman.config'],
                $app['addons.newsman.logger'],
                $app['addons.newsman.service.save_integration'],
                $app['addons.newsman.service.get_remarketing_settings']
            );
        };

        $app['addons.newsman.service.export_csv_subscribers'] = static function ($app) {
            return new Service\ExportCsvSubscribers(
                $app['addons.newsman.api.client'],
                $app['addons.newsman.config'],
                $app['addons.newsman.logger']
            );
        };

        $app['addons.newsman.service.save_orders'] = static function ($app) {
            return new Service\Remarketing\SaveOrders(
                $app['addons.newsman.api.client'],
                $app['addons.newsman.config'],
                $app['addons.newsman.logger']
            );
        };

        // SMS services
        $app['addons.newsman.service.sms.subscribe'] = static function ($app) {
            return new Service\Sms\Subscribe($app['addons.newsman.api.client'], $app['addons.newsman.config']);
        };

        $app['addons.newsman.service.sms.unsubscribe'] = static function ($app) {
            return new Service\Sms\Unsubscribe($app['addons.newsman.api.client'], $app['addons.newsman.config']);
        };

        $app['addons.newsman.service.sms.send_one'] = static function ($app) {
            return new Service\Sms\SendOne($app['addons.newsman.api.client']);
        };

        // Actions
        $app['addons.newsman.action.subscribe'] = static function ($app) {
            return new SubscribeEmail(
                $app['addons.newsman.config'],
                $app['addons.newsman.logger'],
                $app['addons.newsman.service.subscribe'],
                $app['addons.newsman.service.init_subscribe'],
                $app['addons.newsman.service.unsubscribe'],
                $app['addons.newsman.service.segment_add'],
                $app['addons.newsman.user.ip_address']
            );
        };

        $app['addons.newsman.action.order.save'] = static function ($app) {
            return new OrderSave(
                $app['addons.newsman.config'],
                $app['addons.newsman.logger'],
                $app['addons.newsman.service.save_orders']
            );
        };

        $app['addons.newsman.action.order.status'] = static function ($app) {
            return new OrderStatus(
                $app['addons.newsman.config'],
                $app['addons.newsman.logger'],
                $app['addons.newsman.service.set_purchase_status']
            );
        };

        // Hook Handlers
        $app['addons.newsman.hook_handlers.profile'] = static function ($app) {
            return new ProfileHookHandler(
                $app['addons.newsman.config'],
                $app['addons.newsman.action.subscribe'],
                $app['addons.newsman.logger']
            );
        };

        $app['addons.newsman.hook_handlers.order'] = static function ($app) {
            return new OrderHookHandler(
                $app['addons.newsman.config'],
                $app['addons.newsman.action.order.save'],
                $app['addons.newsman.action.order.status'],
                $app['addons.newsman.logger']
            );
        };

        $app['addons.newsman.hook_handlers.newsletter'] = static function ($app) {
            return new NewsletterHookHandler(
                $app['addons.newsman.config'],
                $app['addons.newsman.action.subscribe'],
                $app['addons.newsman.logger']
            );
        };

        $app['addons.newsman.hook_handlers.remarketing'] = static function ($app) {
            $config = $app['addons.newsman.config'];
            return new RemarketingHookHandler(
                $app,
                $config,
                new RemarketingRenderer(
                    $config,
                    new TrackingScript($config),
                    new CartTracking(),
                    new CartTrackingNative(),
                    new CustomerIdentify(),
                    new CustomerIdentifyDeferred(),
                    new ProductView(),
                    new CategoryView(),
                    new PageView(),
                    new Purchase($config)
                ),
                $app['addons.newsman.logger']
            );
        };

        // Export system
        $app['addons.newsman.export.renderer'] = static function () {
            return new ExportRenderer();
        };

        $app['addons.newsman.export.authenticator'] = static function ($app) {
            return new Authenticator($app['addons.newsman.config']);
        };

        $app['addons.newsman.export.retrievers'] = static function ($app) {
            $config = $app['addons.newsman.config'];
            $logger = $app['addons.newsman.logger'];
            $client = $app['addons.newsman.api.client'];
            return array(
                'customers'                => new Export\Retriever\Customers($config, $logger),
                'subscribers'              => new Export\Retriever\Subscribers($config, $logger),
                'products'                 => new Export\Retriever\Products($config, $logger),
                'products-feed'            => new Export\Retriever\Products($config, $logger),
                'orders'                   => new Export\Retriever\Orders($config, $logger),
                'coupons'                  => new Export\Retriever\Coupons($config, $logger),
                'custom-sql'               => new Export\Retriever\CustomSql($config, $logger),
                'platform-name'            => new Export\Retriever\PlatformName($config, $logger),
                'platform-version'         => new Export\Retriever\PlatformVersion($config, $logger),
                'platform-language'        => new Export\Retriever\PlatformLanguage($config, $logger),
                'platform-language-version' => new Export\Retriever\PlatformLanguageVersion($config, $logger),
                'integration-name'         => new Export\Retriever\IntegrationName($config, $logger),
                'integration-version'      => new Export\Retriever\IntegrationVersion($config, $logger),
                'server-ip'                => new Export\Retriever\ServerIp($config, $logger, $app['addons.newsman.util.server_ip_resolver']),
                'server-cloudflare'        => new Export\Retriever\ServerCloudflare($config, $logger),
                'sql-name'                 => new Export\Retriever\SqlName($config, $logger),
                'sql-version'              => new Export\Retriever\SqlVersion($config, $logger),
                'refresh-remarketing'      => new Export\Retriever\RefreshRemarketing($config, $logger, $client, $app['addons.newsman.service.configuration.integration']),
                'subscriber-subscribe'     => new Export\Retriever\SubscriberSubscribe($config, $logger),
                'subscriber-unsubscribe'   => new Export\Retriever\SubscriberUnsubscribe($config, $logger),
                'send-subscribers'         => new Export\Retriever\SendSubscribers($config, $logger, $app['addons.newsman.service.export_csv_subscribers']),
                'cron-subscribers'         => new Export\Retriever\CronSubscribers($config, $logger, $app['addons.newsman.service.export_csv_subscribers']),
                'send-orders'              => new Export\Retriever\SendOrders($config, $logger, $app['addons.newsman.service.save_orders']),
                'cron-orders'              => new Export\Retriever\CronOrders($config, $logger, $app['addons.newsman.service.save_orders']),
            );
        };

        $app['addons.newsman.export.processor'] = static function ($app) {
            return new Processor(
                $app['addons.newsman.export.retrievers'],
                $app['addons.newsman.export.authenticator'],
                $app['addons.newsman.config'],
                $app['addons.newsman.logger']
            );
        };

        $app['addons.newsman.export.router'] = static function ($app) {
            return new Router(
                $app['addons.newsman.config'],
                $app['addons.newsman.logger'],
                new PayloadParser(),
                $app['addons.newsman.export.processor'],
                $app['addons.newsman.export.renderer']
            );
        };

        // Webhooks
        $app['addons.newsman.webhooks'] = static function ($app) {
            return new Webhooks(
                $app['addons.newsman.config'],
                $app['addons.newsman.logger'],
                $app['addons.newsman.export.renderer']
            );
        };
    }
}
