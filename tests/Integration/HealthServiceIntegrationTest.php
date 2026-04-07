<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\RequestContext;
use App\Services\HealthService;
use Tests\Support\IntegrationTestCase;

final class HealthServiceIntegrationTest extends IntegrationTestCase
{
    public function testHealthServiceReturnsLiveAndReadySnapshotsForHealthyApp(): void
    {
        $context = $this->app->get(RequestContext::class);
        $context->startHttp('health-pass');

        $service = $this->app->get(HealthService::class);

        $live = $service->live();
        $ready = $service->ready();

        self::assertSame('pass', $live['status']);
        self::assertSame('health-pass', $live['request_id']);
        self::assertSame('pass', $ready['status']);
        self::assertNotEmpty($ready['checks']);
        self::assertNotEmpty($service->deploymentReadiness());
        self::assertNotEmpty($service->directories());
        self::assertNotEmpty($service->assets());
    }

    public function testHealthServiceCoversReadinessFailureBranch(): void
    {
        $context = new RequestContext();
        $context->startHttp('health-fail');
        $service = new HealthService(
            new Database(
                new Config([
                    'db' => [
                        'driver' => 'sqlite',
                        'database' => dirname(__DIR__, 2) . '/missing/health.sqlite',
                    ],
                ]),
                new Logger(sys_get_temp_dir() . '/sims-health-failure.log', $context)
            ),
            new Config([
                'app' => [
                    'version' => '',
                ],
                'session' => [
                    'path' => dirname(__DIR__, 2) . '/storage/framework/sessions',
                ],
            ]),
            $context,
            dirname(__DIR__, 2)
        );

        $ready = $service->ready();

        self::assertSame('fail', $ready['status']);
        self::assertSame('health-fail', $ready['request_id']);
        self::assertSame('database_connectivity', $ready['checks'][0]['name']);
        self::assertStringContainsString('Unable to connect to the database.', $ready['checks'][0]['message']);
        self::assertSame('schema_required_tables', $ready['checks'][1]['name']);
        self::assertSame('schema_required_columns', $ready['checks'][2]['name']);
    }
}
