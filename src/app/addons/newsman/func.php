<?php

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

$nzmAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($nzmAutoload)) {
    require_once $nzmAutoload;
}
