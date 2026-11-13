<?php

use Tygh\Registry;
use Tygh\Tygh;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

/** @var string $mode */

// Newsletters > Subscribers delete / m_delete: anonymise in Newsman the email
// of every subscriber the core really deleted. subscribers.pre.php captured
// the emails while the rows still existed.
//
// A CS-Cart subscriber belongs to no storefront (the admin subscriber pages
// always work in "All stores" mode), and the contact can be on a Newsman list
// without being on the CS-Cart mailing list selected for that storefront, so
// the email is anonymised in the Newsman list of every storefront where
// Newsman is connected, one call per Newsman list.

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($mode !== 'delete' && $mode !== 'm_delete')) {
    return;
}

$deletedSubscribers = Registry::ifGet('runtime.newsman.deleted_subscribers', array());
Registry::set('runtime.newsman.deleted_subscribers', array());
if (empty($deletedSubscribers) || !is_array($deletedSubscribers)) {
    return;
}

try {
    /** @var \Tygh\Addons\Newsman\Action\Anonymise\Email $anonymiseAction */
    $anonymiseAction = Tygh::$app['addons.newsman.action.anonymise'];
    /** @var \Tygh\Addons\Newsman\Logger $logger */
    $logger = Tygh::$app['addons.newsman.logger'];

    $stillThere = array_map('intval', db_get_fields(
        'SELECT subscriber_id FROM ?:subscribers WHERE subscriber_id IN (?n)',
        array_keys($deletedSubscribers)
    ));
    $storefrontIds = $anonymiseAction->getConnectedStorefrontIds();

    foreach ($deletedSubscribers as $subscriberId => $email) {
        if (in_array((int) $subscriberId, $stillThere, true)) {
            $logger->debug(sprintf('subscribers.post: subscriber_id=%d was not deleted, nothing sent to Newsman', $subscriberId));
            continue;
        }
        $anonymiseAction->execute($email, $storefrontIds, sprintf('subscriber delete subscriber_id=%d', $subscriberId));
    }
} catch (\Throwable $e) {
    // Never break the delete because of Newsman.
    try {
        Tygh::$app['addons.newsman.logger']->logException($e);
    } catch (\Throwable $ignored) {
    }
}
