<?php

declare(strict_types=1);

use App\Core\Logger;
use App\Core\RequestContext;

$root = dirname(__DIR__);

/** @var \App\Core\Application $app */
$app = require $root . '/config/app.php';

$level = $argv[1] ?? 'info';
$message = $argv[2] ?? 'Release event';
$stage = $argv[3] ?? 'unknown';
$backupId = $argv[4] ?? '';
$event = $argv[5] ?? '';

$context = $app->get(RequestContext::class);
$context->startConsole('release-check');

$payload = [
    'event' => $event !== ''
        ? $event
        : ($level === 'error'
            ? 'release.failed'
            : ($stage === 'release:complete' ? 'release.completed' : 'release.event')),
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
