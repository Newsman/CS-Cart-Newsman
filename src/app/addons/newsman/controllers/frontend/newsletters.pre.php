<?php

use Tygh\Tygh;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

/** @var string $mode */

// Bridge CS-Cart gap: newsletters.unsubscribe (email-link unsubscribe) does a
// raw DELETE on ?:user_mailing_lists / ?:subscribers and never calls
// fn_update_subscriptions, so newsletters_update_subscriptions_post never
// fires. Fire our Newsman unsubscribe here before core runs the DELETE.

if ($mode !== 'unsubscribe') {
    return;
}
if (empty($_REQUEST['key']) || empty($_REQUEST['list_id']) || empty($_REQUEST['s_id'])) {
    return;
}

/** @var \Tygh\Addons\Newsman\Config $config */
$config = Tygh::$app['addons.newsman.config'];
if (!$config->isEnabled()) {
    return;
}
$targetListId = (int) $config->getCscartMailingListId();
if ($targetListId === 0 || (int) $_REQUEST['list_id'] !== $targetListId) {
    return;
}

// Validate the same token CS-Cart validates — only fire on a genuine request.
$num = (int) db_get_field(
    "SELECT COUNT(*) FROM ?:user_mailing_lists WHERE unsubscribe_key = ?s AND list_id = ?i AND subscriber_id = ?i",
    $_REQUEST['key'],
    (int) $_REQUEST['list_id'],
    (int) $_REQUEST['s_id']
);
if ($num === 0) {
    return;
}

$email = (string) db_get_field("SELECT email FROM ?:subscribers WHERE subscriber_id = ?i", (int) $_REQUEST['s_id']);
if ($email === '') {
    return;
}

/** @var \Tygh\Addons\Newsman\Logger $logger */
$logger = Tygh::$app['addons.newsman.logger'];
try {
    $logger->info('newsletters.pre: email-link unsubscribe for ' . $email);
    /** @var \Tygh\Addons\Newsman\Action\Subscribe\Email $action */
    $action = Tygh::$app['addons.newsman.action.subscribe'];
    $action->unsubscribe($email);
} catch (\Exception $e) {
    $logger->logException($e);
}
