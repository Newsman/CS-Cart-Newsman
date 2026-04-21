<?php

use Tygh\Registry;
use Tygh\Tygh;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

/** @var string $mode */

// Admin subscribers.manage multistore bridge:
//  * CS-Cart forces admin subscriber dispatches to company_id=0 (see
//    schemas/storefronts/switcher_dispatches.post.php — subscribers=false),
//    so the post-hook only syncs the root storefront's Newsman account.
//    Here we enumerate every storefront with Newsman configured and fire
//    the correct subscribe/unsubscribe per storefront based on the diff
//    between old and new list_ids.
//  * fn_delete_subscribers has no post-hook at all — delete/m_delete are
//    handled entirely here.
//  * runtime.newsman.admin_handled is set so NewsletterHookHandler skips
//    the update modes (it would otherwise double-fire for the root).
//
// Semantics per mode:
//  * update / m_update: `replace` — submitted list_ids replace the current
//    membership. subscriber_id may be 0 when the admin enters a new email;
//    we resolve via email lookup (may still be 0 if not yet in DB).
//  * add_users: `add` — submitted list_id is appended to current membership;
//    other lists are untouched. subscriber_id is always 0 here (resolved
//    via the selected user's email).
//  * delete / m_delete: subscriber is removed entirely.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return;
}

$handledModes = array('update', 'm_update', 'delete', 'm_delete', 'add_users');
if (!in_array($mode, $handledModes, true)) {
    return;
}

/** @var \Tygh\Addons\Newsman\Config $config */
$config = Tygh::$app['addons.newsman.config'];

if (!$config->isActive()) {
    return;
}

// Resolve each storefront's (company_id, settings) up front. Config::readValue()
// with only a storefront_id scopes by the runtime company_id (which is 0 in
// admin subscribers.*), so it would silently return the root defaults for
// every storefront. Query Settings explicitly with the storefront's own
// company_id so per-storefront overrides apply.
$storefrontConfigs = array();
foreach ($config->getAllStorefrontIds() as $sfId) {
    $companyId = (int) db_get_field(
        'SELECT company_id FROM ?:storefronts_companies WHERE storefront_id = ?i LIMIT 1',
        $sfId
    );
    $sfSettings = \Tygh\Settings::instance(array(
        'area'          => 'A',
        'company_id'    => $companyId,
        'storefront_id' => $sfId,
    ))->getValues('newsman', \Tygh\Settings::ADDON_SECTION, false, $companyId, $sfId);
    $sfSettings = is_array($sfSettings) ? $sfSettings : array();

    $apiKey = isset($sfSettings['api_key']) ? $sfSettings['api_key'] : '';
    $listId = isset($sfSettings['list_id']) ? $sfSettings['list_id'] : '';
    $targetListId = isset($sfSettings['cscart_mailing_list_id']) ? (int) $sfSettings['cscart_mailing_list_id'] : 0;
    if ($apiKey === '' || $listId === '' || $targetListId === 0) {
        continue;
    }
    $storefrontConfigs[$sfId] = array(
        'company_id' => $companyId,
        'target'     => $targetListId,
        'settings'   => $sfSettings,
    );
}
if (empty($storefrontConfigs)) {
    return;
}

$targetListIds = array();
foreach ($storefrontConfigs as $cfg) {
    $targetListIds[] = $cfg['target'];
}
$targetListIds = array_values(array_unique($targetListIds));

// Build the operation list: each entry describes one subscriber-level change.
//   subscriber_id: int (0 if unknown yet — resolved via email below)
//   email: string (empty for delete modes — resolved via subscriber_id below)
//   op: 'replace' | 'add' | 'delete'
//   listIds: int[] — meaning depends on op
$operations = array();
$parseIds = function ($raw) {
    return is_array($raw)
        ? array_values(array_filter(array_map('intval', $raw)))
        : array();
};

if ($mode === 'update') {
    $operations[] = array(
        'subscriber_id' => isset($_REQUEST['subscriber_id']) ? (int) $_REQUEST['subscriber_id'] : 0,
        'email'         => isset($_REQUEST['subscriber_data']['email']) ? trim($_REQUEST['subscriber_data']['email']) : '',
        'op'            => 'replace',
        'listIds'       => $parseIds(isset($_REQUEST['subscriber_data']['list_ids']) ? $_REQUEST['subscriber_data']['list_ids'] : array()),
    );
} elseif ($mode === 'm_update' && !empty($_REQUEST['subscribers']) && is_array($_REQUEST['subscribers'])) {
    foreach ($_REQUEST['subscribers'] as $subId => $data) {
        $operations[] = array(
            'subscriber_id' => (int) $subId,
            'email'         => isset($data['email']) ? trim($data['email']) : '',
            'op'            => 'replace',
            'listIds'       => $parseIds(isset($data['list_ids']) ? $data['list_ids'] : array()),
        );
    }
} elseif ($mode === 'delete' && !empty($_REQUEST['subscriber_id'])) {
    $operations[] = array(
        'subscriber_id' => (int) $_REQUEST['subscriber_id'],
        'email'         => '',
        'op'            => 'delete',
        'listIds'       => array(),
    );
} elseif ($mode === 'm_delete' && !empty($_REQUEST['subscriber_ids']) && is_array($_REQUEST['subscriber_ids'])) {
    foreach ($_REQUEST['subscriber_ids'] as $subId) {
        $operations[] = array(
            'subscriber_id' => (int) $subId,
            'email'         => '',
            'op'            => 'delete',
            'listIds'       => array(),
        );
    }
} elseif ($mode === 'add_users' && !empty($_REQUEST['add_users']) && !empty($_REQUEST['list_id'])) {
    $userIds = array_values(array_filter(array_map('intval', (array) $_REQUEST['add_users'])));
    $listId = (int) $_REQUEST['list_id'];
    if (!empty($userIds) && $listId > 0) {
        $users = db_get_array(
            'SELECT user_id, email FROM ?:users WHERE user_id IN (?n)',
            $userIds
        );
        foreach ($users as $user) {
            if (empty($user['email'])) {
                continue;
            }
            $operations[] = array(
                'subscriber_id' => 0,
                'email'         => $user['email'],
                'op'            => 'add',
                'listIds'       => array($listId),
            );
        }
    }
}
if (empty($operations)) {
    return;
}

// Resolve missing email (delete modes) or missing subscriber_id (update with
// subscriber_id=0, or add_users). A subscriber_id of 0 after this lookup
// means the subscriber doesn't exist yet — treat as "not on any list" below.
$emailsToResolve = array();
$idsToResolve = array();
foreach ($operations as $op) {
    if ($op['subscriber_id'] === 0 && $op['email'] !== '') {
        $emailsToResolve[strtolower($op['email'])] = $op['email'];
    } elseif ($op['subscriber_id'] > 0 && $op['email'] === '') {
        $idsToResolve[$op['subscriber_id']] = true;
    }
}
$subIdByEmail = array();
if (!empty($emailsToResolve)) {
    $rows = db_get_array(
        'SELECT subscriber_id, email FROM ?:subscribers WHERE email IN (?a)',
        array_values($emailsToResolve)
    );
    foreach ($rows as $r) {
        $subIdByEmail[strtolower($r['email'])] = (int) $r['subscriber_id'];
    }
}
$emailBySubId = array();
if (!empty($idsToResolve)) {
    $rows = db_get_array(
        'SELECT subscriber_id, email FROM ?:subscribers WHERE subscriber_id IN (?n)',
        array_keys($idsToResolve)
    );
    foreach ($rows as $r) {
        $emailBySubId[(int) $r['subscriber_id']] = $r['email'];
    }
}
foreach ($operations as &$op) {
    if ($op['subscriber_id'] === 0 && $op['email'] !== '') {
        $key = strtolower($op['email']);
        $op['subscriber_id'] = isset($subIdByEmail[$key]) ? $subIdByEmail[$key] : 0;
    } elseif ($op['subscriber_id'] > 0 && $op['email'] === '') {
        $op['email'] = isset($emailBySubId[$op['subscriber_id']]) ? $emailBySubId[$op['subscriber_id']] : '';
    }
}
unset($op);

// Fetch current (target-list-scoped) membership for every resolved subscriber.
$resolvedSubIds = array();
foreach ($operations as $op) {
    if ($op['subscriber_id'] > 0) {
        $resolvedSubIds[$op['subscriber_id']] = true;
    }
}
$currentListsBySubId = array();
if (!empty($resolvedSubIds)) {
    $rows = db_get_array(
        'SELECT subscriber_id, list_id FROM ?:user_mailing_lists'
        . ' WHERE subscriber_id IN (?n) AND list_id IN (?n)',
        array_keys($resolvedSubIds),
        $targetListIds
    );
    foreach ($rows as $r) {
        $currentListsBySubId[(int) $r['subscriber_id']][] = (int) $r['list_id'];
    }
}

/** @var \Tygh\Addons\Newsman\Logger $logger */
$logger = Tygh::$app['addons.newsman.logger'];
/** @var \Tygh\Addons\Newsman\Action\Subscribe\Email $action */
$action = Tygh::$app['addons.newsman.action.subscribe'];

// Registry keys the SubscribeAction / ApiClient read at call time — swapped
// per storefront, then restored below. Keeping the list explicit avoids
// scoping too wide (e.g. remarketing keys) or too narrow (missing api_key).
$swappableKeys = array(
    'api_key', 'user_id', 'list_id', 'segment_id', 'authenticate_token',
    'cscart_mailing_list_id', 'double_optin', 'send_user_ip', 'server_ip',
    'api_timeout', 'log_severity', 'log_clean_days',
);
$savedRegistry = array();
foreach ($swappableKeys as $k) {
    $savedRegistry[$k] = Registry::get('addons.newsman.' . $k);
}
$savedRuntimeCompanyId = Registry::get('runtime.company_id');
$savedRuntimeStorefrontId = Registry::get('runtime.storefront_id');

foreach ($operations as $op) {
    $email = $op['email'];
    if ($email === '') {
        continue;
    }
    // delete of a non-existent subscriber_id: nothing to unsubscribe.
    if ($op['op'] === 'delete' && $op['subscriber_id'] === 0) {
        continue;
    }

    $currentLists = ($op['subscriber_id'] > 0 && isset($currentListsBySubId[$op['subscriber_id']]))
        ? $currentListsBySubId[$op['subscriber_id']]
        : array();

    foreach ($storefrontConfigs as $sfId => $cfg) {
        $targetListId = $cfg['target'];
        $wasOn = in_array($targetListId, $currentLists, true);

        if ($op['op'] === 'delete') {
            $willBeOn = false;
        } elseif ($op['op'] === 'replace') {
            $willBeOn = in_array($targetListId, $op['listIds'], true);
        } else { // 'add' (additive — existing memberships are preserved)
            $willBeOn = $wasOn || in_array($targetListId, $op['listIds'], true);
        }

        if ($wasOn === $willBeOn) {
            continue;
        }

        // Swap Registry to this storefront's Newsman config so SubscribeAction
        // / ApiClient (which read from runtime Registry at call time) pick up
        // the right api_key / list_id / user_id.
        foreach ($swappableKeys as $k) {
            if (array_key_exists($k, $cfg['settings'])) {
                Registry::set('addons.newsman.' . $k, $cfg['settings'][$k]);
            }
        }
        Registry::set('runtime.storefront_id', $sfId);
        Registry::set('runtime.company_id', $cfg['company_id']);

        try {
            if ($wasOn && !$willBeOn) {
                $logger->info('subscribers.pre: admin ' . $mode . ' storefront=' . $sfId . ' unsubscribe for ' . $email);
                $action->unsubscribe($email);
            } else {
                $logger->info('subscribers.pre: admin ' . $mode . ' storefront=' . $sfId . ' subscribe for ' . $email);
                $action->subscribe($email);
            }
        } catch (\Exception $e) {
            $logger->logException($e);
        }
    }
}

// Restore Registry.
foreach ($swappableKeys as $k) {
    Registry::set('addons.newsman.' . $k, $savedRegistry[$k]);
}
Registry::set('runtime.company_id', $savedRuntimeCompanyId);
Registry::set('runtime.storefront_id', $savedRuntimeStorefrontId);

// Tell NewsletterHookHandler we already handled admin update/add modes.
Registry::set('runtime.newsman.admin_handled', true);
