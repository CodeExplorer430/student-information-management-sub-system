<?php

declare(strict_types=1);

use App\Core\Logger;
use App\Core\RequestContext;

$root = dirname(__DIR__);

/** @var \App\Core\Application $app */
$app = require $root . '/config/app.php';

$level = $argv[1] ?? 'info';
$event = $argv[2] ?? 'operations.event';
$message = $argv[3] ?? 'Operations event';
$stage = $argv[4] ?? 'unknown';
$backupId = $argv[5] ?? '';

$context = $app->get(RequestContext::class);
$context->startConsole($event);

$payload = [
    'event' => $event,
    'stage' => $stage,
];

if ($backupId !== '') {
    $payload['backup_id'] = $backupId;
}

$logger = $app->get(Logger::class);

if ($level === 'error') {
    $logger->error($message, $payload, 'operations');
    exit(0);
}

if ($level === 'warning') {
    $logger->warning($message, $payload, 'operations');
    exit(0);
}

$logger->info($message, $payload, 'operations');
