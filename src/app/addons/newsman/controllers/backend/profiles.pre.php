<?php

use Tygh\Tygh;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

/** @var string $mode */

// GDPR anonymize (Customers > anonymize): the core profiles.anonymize mode
// replaces the user's email with a random one and fires no hook of its own.
// Capture the real email here, before that happens. UserHookHandler sends the
// Newsman unsubscribe on update_user_pre, which fires only when the core
// controller really saves the anonymized data (not when the request is
// refused, e.g. the user is not a customer or was already anonymized).

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $mode !== 'anonymize' || empty($_REQUEST['user_id'])) {
    return;
}

try {
    /** @var \Tygh\Addons\Newsman\HookHandlers\UserHookHandler $userHookHandler */
    $userHookHandler = Tygh::$app['addons.newsman.hook_handlers.user'];
    $userHookHandler->captureAnonymization((int) $_REQUEST['user_id']);
} catch (\Throwable $e) {
    // Never block the anonymization because of Newsman.
    try {
        Tygh::$app['addons.newsman.logger']->logException($e);
    } catch (\Throwable $ignored) {
    }
}
