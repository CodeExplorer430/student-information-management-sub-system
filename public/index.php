<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\HttpEmitter;
use App\Core\HttpKernel;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var Application $app */
$app = require dirname(__DIR__) . '/config/app.php';

$app->get(HttpEmitter::class)->emit($app->get(HttpKernel::class)->handle(
    string_value($_SERVER['REQUEST_METHOD'] ?? 'GET', 'GET'),
    string_value($_SERVER['REQUEST_URI'] ?? '/', '/')
));
