<?php

use Tygh\Enum\YesNo;
use Tygh\Registry;
use Tygh\Tygh;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

/** @var string $mode */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}
if ($mode !== 'update') {
    return;
}
if (Registry::ifGet('runtime.profile_updated', YesNo::YES) !== YesNo::YES) {
    return;
}

/** @var \Tygh\Addons\Newsman\Config $config */
$config = Tygh::$app['addons.newsman.config'];
if (!$config->isEnabled()) {
    return;
}
$targetListId = (int) $config->getCscartMailingListId();
if ($targetListId === 0) {
    return;
}

$email = isset($_REQUEST['user_data']['email']) ? (string) $_REQUEST['user_data']['email'] : '';
if ($email === '') {
    return;
}

// Capture pre-state: was this email already subscribed to the configured list
// BEFORE fn_update_subscriptions runs. NewsletterHookHandler reads this in the
// post-hook so it can distinguish "existing subscriber unsubscribes" (which
// should fire the Newsman unsubscribe) from "new or never-subscribed user
// ticks only a non-configured list" (which should not).
$preSubscribed = false;
$preSubscriberId = (int) db_get_field("SELECT subscriber_id FROM ?:subscribers WHERE email = ?s", $email);
if ($preSubscriberId !== 0) {
    $preSubscribed = (int) db_get_field(
        "SELECT COUNT(*) FROM ?:user_mailing_lists WHERE subscriber_id = ?i AND list_id = ?i",
        $preSubscriberId,
        $targetListId
    ) > 0;
}
$preState = (array) Registry::ifGet('runtime.newsman.pre_subscribed', array());
$preState[strtolower($email)] = $preSubscribed;
Registry::set('runtime.newsman.pre_subscribed', $preState);

// Bridge CS-Cart gap: when the user unchecks EVERY mailing list,
// fn_update_subscriptions calls fn_delete_subscribers BEFORE firing
// newsletters_update_subscriptions_post, so the hook receives an empty
// $subscriber and we can no longer resolve the email. Catch that case here.
if (!empty($_REQUEST['mailing_lists'])) {
    return;
}
if (!$preSubscribed) {
    return;
}

/** @var \Tygh\Addons\Newsman\Logger $logger */
$logger = Tygh::$app['addons.newsman.logger'];
try {
    $logger->info('profiles.pre: full unsubscribe detected for ' . $email);
    /** @var \Tygh\Addons\Newsman\Action\Subscribe\Email $action */
    $action = Tygh::$app['addons.newsman.action.subscribe'];
    $action->unsubscribe($email);
} catch (\Exception $e) {
    $logger->logException($e);
}
