<?php

$autoload = __DIR__ . '/../../../autoload.php';

if (!file_exists($autoload)) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
}
require_once $autoload;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT & ~E_DEPRECATED);
