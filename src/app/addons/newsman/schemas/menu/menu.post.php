<?php

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

$schema['central']['marketing']['items']['newsman'] = array(
    'attrs' => array('class' => 'is-addon'),
    'href'  => 'newsman.manage',
    'position' => 900,
);

return $schema;
