<?php

declare(strict_types=1);

$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'false';
$appUrl = getenv('APP_URL');
if ($appUrl === false || $appUrl === '') {
    $appUrl = getenv('E2E_APP_URL');
}

if ($appUrl === false || $appUrl === '') {
    $appUrl = 'http://127.0.0.1:18081';
}

$_ENV['APP_URL'] = $appUrl;
$_SERVER['APP_URL'] = $appUrl;
$_ENV['APP_KEY'] = 'testing-key';
$_ENV['APP_TIMEZONE'] = 'Asia/Manila';
$_ENV['DB_DRIVER'] = 'sqlite';
$_ENV['DB_DATABASE'] = dirname(__DIR__) . '/storage/database/test.sqlite';
$_ENV['SESSION_NAME'] = 'simstestsession';
$_ENV['SESSION_LIFETIME'] = '120';
$_ENV['DEFAULT_PASSWORD'] = 'Password123!';

require dirname(__DIR__) . '/vendor/autoload.php';
