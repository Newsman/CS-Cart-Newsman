<?php

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

$schema['newsman'] = array(
    'permissions' => 'manage_newsman',
    'modes' => array(
        'manage' => array(
            'permissions' => 'view_newsman',
        ),
        'fetch_lists' => array(
            'permissions' => 'manage_newsman',
        ),
        'fetch_segments' => array(
            'permissions' => 'manage_newsman',
        ),
    ),
);

return $schema;
