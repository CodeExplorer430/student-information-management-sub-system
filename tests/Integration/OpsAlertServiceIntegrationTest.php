<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Application;
use App\Core\Config;
use App\Core\Logger;
use App\Repositories\NotificationRepository;
use App\Repositories\UserRepository;
use App\Services\BackupService;
use App\Services\OpsAlertService;
use App\Services\S3BackupRemoteStore;
use ReflectionMethod;
use Tests\Support\IntegrationTestCase;

final class OpsAlertServiceIntegrationTest extends IntegrationTestCase
{
    /**
     * @var array<string, array{body: string, headers: array<string, string>}>
     */
    private array $remoteObjects = [];

    protected function setUp(): void
    {
        $_ENV['BACKUP_REMOTE_DRIVER'] = 's3';
        $_SERVER['BACKUP_REMOTE_DRIVER'] = 's3';
        $_ENV['BACKUP_REMOTE_BUCKET'] = 'test-bucket';
        $_SERVER['BACKUP_REMOTE_BUCKET'] = 'test-bucket';
        $_ENV['BACKUP_REMOTE_REGION'] = 'us-east-1';
        $_SERVER['BACKUP_REMOTE_REGION'] = 'us-east-1';
        $_ENV['BACKUP_REMOTE_ENDPOINT'] = 'https://s3.example.test';
        $_SERVER['BACKUP_REMOTE_ENDPOINT'] = 'https://s3.example.test';
        $_ENV['BACKUP_REMOTE_ACCESS_KEY'] = 'access-key';
        $_SERVER['BACKUP_REMOTE_ACCESS_KEY'] = 'access-key';
        $_ENV['BACKUP_REMOTE_SECRET_KEY'] = 'secret-key';
        $_SERVER['BACKUP_REMOTE_SECRET_KEY'] = 'secret-key';
        $_ENV['BACKUP_REMOTE_PREFIX'] = 'exports';
        $_SERVER['BACKUP_REMOTE_PREFIX'] = 'exports';
        $_ENV['BACKUP_REMOTE_PATH_STYLE'] = 'true';
        $_SERVER['BACKUP_REMOTE_PATH_STYLE'] = 'true';
        $_ENV['BACKUP_EXPORT_KEY'] = 'test-export-key';
        $_SERVER['BACKUP_EXPORT_KEY'] = 'test-export-key';

        parent::setUp();

        file_put_contents($this->app->rootPath('storage/logs/app.log'), '');

        $this->app->singleton(S3BackupRemoteStore::class, fn (Application $app): S3BackupRemoteStore => new S3BackupRemoteStore(
            $app->get(Config::class),
            $this->remoteRequestHandler()
        ));
    }

    protected function tearDown(): void
    {
        unset(
            $_ENV['BACKUP_REMOTE_DRIVER'],
            $_SERVER['BACKUP_REMOTE_DRIVER'],
            $_ENV['BACKUP_REMOTE_BUCKET'],
            $_SERVER['BACKUP_REMOTE_BUCKET'],
            $_ENV['BACKUP_REMOTE_REGION'],
            $_SERVER['BACKUP_REMOTE_REGION'],
            $_ENV['BACKUP_REMOTE_ENDPOINT'],
            $_SERVER['BACKUP_REMOTE_ENDPOINT'],
            $_ENV['BACKUP_REMOTE_ACCESS_KEY'],
            $_SERVER['BACKUP_REMOTE_ACCESS_KEY'],
            $_ENV['BACKUP_REMOTE_SECRET_KEY'],
            $_SERVER['BACKUP_REMOTE_SECRET_KEY'],
            $_ENV['BACKUP_REMOTE_PREFIX'],
            $_SERVER['BACKUP_REMOTE_PREFIX'],
            $_ENV['BACKUP_REMOTE_PATH_STYLE'],
            $_SERVER['BACKUP_REMOTE_PATH_STYLE'],
            $_ENV['BACKUP_EXPORT_KEY'],
            $_SERVER['BACKUP_EXPORT_KEY']
        );

        $this->remoteObjects = [];

        parent::tearDown();
    }

    public function testCheckAndDispatchCreatesAndDeduplicatesBackupAlerts(): void
    {
        $service = $this->app->get(OpsAlertService::class);
        $initialCount = count($this->notificationsForAdmin());

        $first = $service->checkAndDispatch();

        self::assertSame('fail', $first['status']);
        self::assertSame(4, $first['counts']['active']);
        self::assertSame(4, $first['counts']['notified']);
        self::assertSame([
            'backup.drill.stale',
            'backup.local.stale',
            'backup.local.unverified',
            'backup.remote.stale',
        ], $first['notified_keys']);

        $adminNotifications = $this->notificationsForAdmin();
        self::assertCount($initialCount + 4, $adminNotifications);

        $second = $service->checkAndDispatch();

        self::assertSame('fail', $second['status']);
        self::assertSame(4, $second['counts']['active']);
        self::assertSame(0, $second['counts']['notified']);
        self::assertSame([], $second['notified_keys']);
        self::assertCount($initialCount + 4, $this->notificationsForAdmin());
    }

    public function testReportMarksRemoteReplicationAndDrillAsStaleWithoutDispatchingNotifications(): void
    {
        $initialCount = count($this->notificationsForAdmin());
        $backups = $this->app->get(BackupService::class);
        $manifest = $backups->create();
        $backups->verify($manifest['id']);
        $backups->export($manifest['id']);
        $push = $backups->push($manifest['id']);

        $this->remoteObjects[$push['object_key']]['headers']['x-amz-meta-created-at'] = '2026-03-01T00:00:00+00:00';

        $report = $this->app->get(OpsAlertService::class)->report();
        $keys = array_map(static fn (array $alert): string => string_value($alert['key']), $report['active_alerts']);
        sort($keys);

        self::assertSame([
            'backup.drill.stale',
            'backup.remote.stale',
        ], $keys);
        self::assertCount($initialCount, $this->notificationsForAdmin());
    }

    public function testCheckAndDispatchSurfacesOperationalFailureEventsAndResolvesThem(): void
    {
        $logger = $this->app->get(Logger::class);
        $logger->error('Automated backup run failed.', [
            'event' => 'backup.run.failed',
            'stage' => 'push',
        ], 'operations');
        $logger->error('Release check failed.', [
            'event' => 'release.failed',
            'stage' => 'migrate',
        ], 'operations');
        $logger->error('Deployment smoke failed.', [
            'event' => 'deployment.smoke.failed',
            'stage' => 'deployment-smoke',
        ], 'operations');

        $first = $this->app->get(OpsAlertService::class)->checkAndDispatch();
        $keys = array_map(static fn (array $alert): string => string_value($alert['key']), $first['active_alerts']);

        self::assertContains('backup.run.failed', $keys);
        self::assertContains('release.failed', $keys);
        self::assertContains('deployment.smoke.failed', $keys);
        self::assertNotSame([], $first['notified_keys']);

        $logger->info('Automated backup run completed.', [
            'event' => 'backup.run.completed',
            'stage' => 'complete',
        ], 'operations');
        $logger->info('Release check completed.', [
            'event' => 'release.completed',
            'stage' => 'release:complete',
        ], 'operations');
        $logger->info('Deployment smoke completed.', [
            'event' => 'deployment.smoke.completed',
            'stage' => 'deployment-smoke',
        ], 'operations');

        $second = $this->app->get(OpsAlertService::class)->checkAndDispatch();
        $remaining = array_map(static fn (array $alert): string => string_value($alert['key']), $second['active_alerts']);

        self::assertNotContains('backup.run.failed', $remaining);
        self::assertNotContains('release.failed', $remaining);
        self::assertNotContains('deployment.smoke.failed', $remaining);
        self::assertContains('backup.run.failed', $second['resolved_keys']);
        self::assertContains('release.failed', $second['resolved_keys']);
        self::assertContains('deployment.smoke.failed', $second['resolved_keys']);
    }

    public function testBackupAlertsSkipUnknownChecksAndFallBackToCurrentTimestampWhenLatestCheckpointIsMissing(): void
    {
        $service = $this->app->get(OpsAlertService::class);
        $method = new ReflectionMethod(OpsAlertService::class, 'backupAlerts');
        $method->setAccessible(true);

        $timestamp = '2026-04-02T00:00:00+00:00';
        $backupReport = [
            'status' => 'fail',
            'timestamp' => $timestamp,
            'request_id' => 'ops-alert-test',
            'counts' => ['local' => 0, 'remote' => 0],
            'thresholds' => [
                'local_hours' => 24,
                'remote_hours' => 24,
                'drill_hours' => 168,
                'remote_enabled' => true,
            ],
            'latest' => [
                'local_backup' => null,
                'verified_backup' => [
                    'backup_id' => null,
                    'created_at' => null,
                    'age_hours' => null,
                    'location' => null,
                ],
                'export' => [
                    'backup_id' => null,
                    'created_at' => null,
                    'age_hours' => null,
                    'location' => null,
                ],
                'remote_push' => [
                    'backup_id' => null,
                    'created_at' => null,
                    'age_hours' => null,
                    'location' => null,
                ],
                'drill' => [
                    'backup_id' => null,
                    'created_at' => null,
                    'age_hours' => null,
                    'location' => null,
                ],
            ],
            'checks' => [
                ['name' => 'unknown_check', 'status' => 'fail', 'message' => 'Ignore me.'],
                ['name' => 'local_backup_recency', 'status' => 'fail', 'message' => 'Fallback detection timestamp.'],
            ],
        ];

        $alerts = $method->invoke($service, $backupReport, [], $timestamp);

        self::assertIsArray($alerts);
        /** @var list<array{key: string, detected_at: string}> $alerts */
        self::assertCount(1, $alerts);
        self::assertSame('backup.local.stale', $alerts[0]['key'] ?? null);
        self::assertSame($timestamp, $alerts[0]['detected_at'] ?? null);
    }

    public function testAlertNotificationCyclesResetWhenResolvedEventIsLogged(): void
    {
        $service = $this->app->get(OpsAlertService::class);
        $method = new ReflectionMethod(OpsAlertService::class, 'alertNotificationCycles');
        $method->setAccessible(true);

        $cycles = $method->invoke($service, [
            [
                'timestamp' => '2026-04-02T01:00:00+00:00',
                'level' => 'INFO',
                'channel' => 'operations',
                'request_id' => 'ops-alert-resolved',
                'message' => 'Operational alert resolved.',
                'context' => [
                    'event' => 'ops.alert.resolved',
                    'alert_key' => 'backup.local.stale',
                ],
            ],
            [
                'timestamp' => '2026-04-02T00:00:00+00:00',
                'level' => 'ERROR',
                'channel' => 'operations',
                'request_id' => 'ops-alert-sent',
                'message' => 'Operational alert dispatched.',
                'context' => [
                    'event' => 'ops.alert.sent',
                    'alert_key' => 'backup.local.stale',
                ],
            ],
        ]);

        self::assertIsArray($cycles);
        /** @var array<string, array{active: bool, first_detected_at: string|null}> $cycles */
        self::assertArrayHasKey('backup.local.stale', $cycles);
        self::assertFalse($cycles['backup.local.stale']['active']);
        self::assertNull($cycles['backup.local.stale']['first_detected_at']);
    }

    /**
     * @return list<NotificationRow>
     */
    private function notificationsForAdmin(): array
    {
        $admin = $this->app->get(UserRepository::class)->findByEmail('admin@bcp.edu');
        self::assertIsArray($admin);

        return $this->app->get(NotificationRepository::class)->forUser((int) $admin['id']);
    }

    /**
     * @return callable(string, string, array<string, string>, string): array{status: int, headers: array<string, string>, body: string}
     */
    private function remoteRequestHandler(): callable
    {
        return function (string $method, string $url, array $headers, string $body): array {
            $parts = parse_url($url);
            self::assertIsArray($parts);
            $path = trim((string) ($parts['path'] ?? ''), '/');
            $segments = $path === '' ? [] : explode('/', $path);
            $objectKey = implode('/', array_slice($segments, 1));

            if ($method === 'PUT') {
                $this->remoteObjects[$objectKey] = [
                    'body' => $body,
                    'headers' => [
                        'content-length' => (string) strlen($body),
                        'x-amz-meta-backup-id' => string_value($headers['x-amz-meta-backup-id'] ?? ''),
                        'x-amz-meta-archive-checksum' => string_value($headers['x-amz-meta-archive-checksum'] ?? ''),
                        'x-amz-meta-encrypted-checksum' => string_value($headers['x-amz-meta-encrypted-checksum'] ?? ''),
                        'x-amz-meta-created-at' => string_value($headers['x-amz-meta-created-at'] ?? ''),
                        'x-amz-meta-manifest-version' => string_value($headers['x-amz-meta-manifest-version'] ?? ''),
                    ],
                ];

                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => '',
                ];
            }

            if ($method === 'GET' && str_contains($url, 'list-type=2')) {
                $items = '';

                foreach (array_keys($this->remoteObjects) as $key) {
                    $items .= '<Contents><Key>' . e($key) . '</Key></Contents>';
                }

                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => '<ListBucketResult>' . $items . '</ListBucketResult>',
                ];
            }

            $object = $this->remoteObjects[$objectKey] ?? null;

            if (!is_array($object)) {
                return [
                    'status' => 404,
                    'headers' => [],
                    'body' => '',
                ];
            }

            if ($method === 'HEAD') {
                return [
                    'status' => 200,
                    'headers' => $object['headers'],
                    'body' => '',
                ];
            }

            if ($method === 'GET') {
                return [
                    'status' => 200,
                    'headers' => $object['headers'],
                    'body' => $object['body'],
                ];
            }

            if ($method === 'DELETE') {
                unset($this->remoteObjects[$objectKey]);

                return [
                    'status' => 204,
                    'headers' => [],
                    'body' => '',
                ];
            }

            return [
                'status' => 405,
                'headers' => [],
                'body' => '',
            ];
        };
    }
}
