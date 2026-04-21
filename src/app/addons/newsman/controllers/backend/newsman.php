<?php

use Tygh\Addons\Newsman\Api\Client;
use Tygh\Addons\Newsman\Api\Context;
use Tygh\Addons\Newsman\Config;
use Tygh\Addons\Newsman\Logger;
use Tygh\Addons\Newsman\Service\Configuration\Integration as IntegrationService;
use Tygh\Addons\Newsman\Service\GetListAll;
use Tygh\Addons\Newsman\Service\GetSegmentAll;
use Tygh\Registry;
use Tygh\Settings;
use Tygh\Tygh;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

/** @var string $mode */

/** @var Config $config */
$config = Tygh::$app['addons.newsman.config'];
/** @var Logger $logger */
$logger = Tygh::$app['addons.newsman.logger'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'save') {
        $settings = Settings::instance();

        // 1. Snapshot old credentials BEFORE writing new values.
        $oldUserId = $config->getUserId();
        $oldApiKey = $config->getApiKey();
        $oldListId = $config->getListId();

        // 2. Persist the POSTed fields.
        $fields = array(
            'api_key', 'user_id', 'list_id', 'segment_id', 'cscart_mailing_list_id',
            'double_optin', 'send_user_ip', 'server_ip',
            'remarketing_status', 'remarketing_id', 'remarketing_anonymize_ip', 'remarketing_send_telephone',
            'log_severity', 'log_clean_days', 'api_timeout',
            'dev_active_user_ip', 'dev_user_ip',
            'export_auth_header_name', 'export_auth_header_key',
        );

        foreach ($fields as $field) {
            if (isset($_REQUEST[$field])) {
                $settings->updateValue($field, $_REQUEST[$field], 'newsman');
            }
        }

        // Unchecked checkboxes aren't sent in POST; default them to N.
        $checkboxes = array(
            'double_optin', 'send_user_ip',
            'remarketing_status', 'remarketing_anonymize_ip', 'remarketing_send_telephone',
            'dev_active_user_ip',
        );
        foreach ($checkboxes as $cb) {
            if (!isset($_REQUEST[$cb])) {
                $settings->updateValue($cb, 'N', 'newsman');
            }
        }

        // 3. Refresh Registry so later Config reads return the new values.
        $newsmanRegistry = Registry::get('addons.newsman');
        if (!is_array($newsmanRegistry)) {
            $newsmanRegistry = array();
        }
        foreach ($fields as $field) {
            if (isset($_REQUEST[$field])) {
                $newsmanRegistry[$field] = $_REQUEST[$field];
            }
        }
        foreach ($checkboxes as $cb) {
            if (!isset($_REQUEST[$cb])) {
                $newsmanRegistry[$cb] = 'N';
            }
        }
        Registry::set('addons.newsman', $newsmanRegistry);

        // 4. Trigger the integration sync only when credentials changed.
        $newUserId = $config->getUserId();
        $newApiKey = $config->getApiKey();
        $newListId = $config->getListId();

        $credentialsChanged = $newUserId !== $oldUserId
            || $newApiKey !== $oldApiKey
            || $newListId !== $oldListId;

        if ($credentialsChanged && $newUserId !== '' && $newApiKey !== '' && $newListId !== '') {
            $storeUrl = fn_url('newsman_front.api', 'C', 'https');
            /** @var IntegrationService $integration */
            $integration = Tygh::$app['addons.newsman.service.configuration.integration'];
            $integration->syncAndPropagate($storeUrl);
        }

        fn_set_notification('N', __('notice'), __('text_changes_saved'));

        return array(CONTROLLER_STATUS_OK, 'newsman.manage');
    }

    if ($mode === 'oauth_save') {
        $userId = isset($_REQUEST['user_id']) ? trim($_REQUEST['user_id']) : '';
        $apiKey = isset($_REQUEST['api_key']) ? trim($_REQUEST['api_key']) : '';
        $listId = isset($_REQUEST['list_id']) ? trim($_REQUEST['list_id']) : '';

        if ($userId === '' || $apiKey === '' || $listId === '') {
            fn_set_notification('E', __('error'), __('newsman.oauth_missing_fields'));

            return array(CONTROLLER_STATUS_OK, 'newsman.oauth_login');
        }

        $settings = Settings::instance();
        $settings->updateValue('user_id', $userId, 'newsman');
        $settings->updateValue('api_key', $apiKey, 'newsman');
        $settings->updateValue('list_id', $listId, 'newsman');

        Registry::set('addons.newsman.user_id', $userId);
        Registry::set('addons.newsman.api_key', $apiKey);
        Registry::set('addons.newsman.list_id', $listId);

        $storeUrl = fn_url('newsman_front.api', 'C', 'https');
        /** @var IntegrationService $integration */
        $integration = Tygh::$app['addons.newsman.service.configuration.integration'];
        $integration->syncAndPropagate($storeUrl);

        fn_set_notification('N', __('notice'), __('newsman.oauth_connected'));

        return array(CONTROLLER_STATUS_OK, 'newsman.manage');
    }
}

if ($mode === 'oauth_login') {
    $callbackUrl = fn_url('newsman.oauth_callback', 'A', 'current');

    $oauthUrl = Config::OAUTH_AUTHORIZE_URL
        . '?response_type=code&client_id=' . Config::OAUTH_CLIENT_ID
        . '&nzmplugin=cscart'
        . '&scope=api&redirect_uri=' . urlencode($callbackUrl);

    Tygh::$app['view']->assign('oauth_url', $oauthUrl);
    Tygh::$app['view']->assign('has_credentials', $config->isConfigured());
}

if ($mode === 'oauth_callback') {
    $error = isset($_REQUEST['error']) ? $_REQUEST['error'] : '';
    if (!empty($error)) {
        fn_set_notification('E', __('error'), __('newsman.oauth_error') . ': ' . $error);

        return array(CONTROLLER_STATUS_OK, 'newsman.oauth_login');
    }

    $code = isset($_REQUEST['code']) ? $_REQUEST['code'] : '';
    if (empty($code)) {
        fn_set_notification('E', __('error'), __('newsman.oauth_missing_code'));

        return array(CONTROLLER_STATUS_OK, 'newsman.oauth_login');
    }

    // Exchange authorization code for access token
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => Config::OAUTH_TOKEN_URL,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(array(
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'client_id'    => Config::OAUTH_CLIENT_ID,
            'redirect_uri' => '',
        )),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
    ));
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!empty($curlError)) {
        fn_set_notification('E', __('error'), __('newsman.oauth_curl_error') . ': ' . $curlError);

        return array(CONTROLLER_STATUS_OK, 'newsman.oauth_login');
    }

    if ($status < 200 || $status >= 300 || empty($body)) {
        fn_set_notification('E', __('error'), __('newsman.oauth_invalid_response') . ' (HTTP ' . $status . ')');

        return array(CONTROLLER_STATUS_OK, 'newsman.oauth_login');
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded) || empty($decoded['user_id']) || empty($decoded['access_token'])) {
        fn_set_notification('E', __('error'), __('newsman.oauth_unexpected_response'));

        return array(CONTROLLER_STATUS_OK, 'newsman.oauth_login');
    }

    $oauthUserId = (string) $decoded['user_id'];
    $oauthApiKey = (string) $decoded['access_token'];

    /** @var Client $client */
    $client = Tygh::$app['addons.newsman.api.client'];
    $context = new Context();
    $context->setUserId($oauthUserId)
            ->setApiKey($oauthApiKey)
            ->setEndpoint('list.all');

    $allLists = $client->get($context);
    $lists = array();

    if (is_array($allLists) && !isset($allLists['error'])) {
        foreach ($allLists as $list) {
            if (isset($list['list_type']) && $list['list_type'] === 'sms') {
                continue;
            }
            $lists[] = $list;
        }
    }

    if (empty($lists)) {
        fn_set_notification('E', __('error'), __('newsman.oauth_no_lists'));

        return array(CONTROLLER_STATUS_OK, 'newsman.oauth_login');
    }

    Tygh::$app['view']->assign('oauth_user_id', $oauthUserId);
    Tygh::$app['view']->assign('oauth_api_key', $oauthApiKey);
    Tygh::$app['view']->assign('oauth_lists', $lists);
}

if ($mode === 'manage') {
    // If not configured, redirect to OAuth login
    if (!$config->isConfigured()) {
        return array(CONTROLLER_STATUS_OK, 'newsman.oauth_login');
    }

    $allSettings = $config->getAllSettings();
    Tygh::$app['view']->assign('newsman_settings', $allSettings);

    // Connection status + fetch lists
    $isConnected = false;
    $lists = array();
    $segments = array();

    if ($config->isConfigured()) {
        /** @var GetListAll $listService */
        $listService = Tygh::$app['addons.newsman.service.get_list_all'];
        $listResult = $listService->execute();
        if (is_array($listResult) && !isset($listResult['error'])) {
            $isConnected = true;
            $lists = $listResult;
        }

        if (!empty($config->getListId())) {
            /** @var GetSegmentAll $segmentService */
            $segmentService = Tygh::$app['addons.newsman.service.get_segment_all'];
            $segmentResult = $segmentService->execute($config->getListId());
            if (is_array($segmentResult) && !isset($segmentResult['error'])) {
                $segments = $segmentResult;
            }
        }
    }

    $remarketingId = isset($allSettings['remarketing_id']) ? $allSettings['remarketing_id'] : '';
    $isRemarketingConnected = !empty($remarketingId);

    Tygh::$app['view']->assign('newsman_version', Config::ADDON_VERSION);
    Tygh::$app['view']->assign('newsman_connected', $isConnected);
    Tygh::$app['view']->assign('newsman_remarketing_connected', $isRemarketingConnected);
    Tygh::$app['view']->assign('newsman_lists', $lists);
    Tygh::$app['view']->assign('newsman_segments', $segments);

    $cscartMailingLists = array();
    if (function_exists('fn_get_mailing_lists')) {
        list($cscartMailingLists) = fn_get_mailing_lists(array('only_available' => false), 0, DESCR_SL);
    }
    Tygh::$app['view']->assign('newsman_cscart_mailing_lists', $cscartMailingLists);
}

if ($mode === 'fetch_lists') {
    if (!$config->isConfigured()) {
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'Not configured'));
        exit;
    }

    /** @var GetListAll $listService */
    $listService = Tygh::$app['addons.newsman.service.get_list_all'];
    $result = $listService->execute();

    header('Content-Type: application/json');
    echo json_encode(is_array($result) ? $result : array());
    exit;
}

if ($mode === 'fetch_segments') {
    $listId = isset($_REQUEST['list_id']) ? $_REQUEST['list_id'] : '';
    if (empty($listId)) {
        header('Content-Type: application/json');
        echo json_encode(array());
        exit;
    }

    /** @var GetSegmentAll $segmentService */
    $segmentService = Tygh::$app['addons.newsman.service.get_segment_all'];
    $result = $segmentService->execute($listId);

    header('Content-Type: application/json');
    echo json_encode(is_array($result) ? $result : array());
    exit;
}
