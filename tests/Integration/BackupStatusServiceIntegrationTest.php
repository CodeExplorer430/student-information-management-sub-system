<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Application;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\RequestContext;
use App\Services\BackupService;
use App\Services\BackupStatusService;
use App\Services\S3BackupRemoteStore;
use App\Support\DatabaseBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class BackupStatusServiceIntegrationTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $temporaryRoots = [];

    /**
     * @var array<string, array{body: string, headers: array<string, string>}>
     */
    private array $remoteObjects = [];

    private bool $failRemoteList = false;

    protected function tearDown(): void
    {
        foreach ($this->temporaryRoots as $root) {
            $this->removeDirectory($root);
        }

        $this->temporaryRoots = [];
        $this->remoteObjects = [];
        $this->failRemoteList = false;

        parent::tearDown();
    }

    public function testReportReturnsHealthyFreshStatusAcrossLocalRemoteAndDrill(): void
    {
        $app = $this->buildApplication();
        $backups = $app->get(BackupService::class);
        $status = $app->get(BackupStatusService::class);

        $manifest = $backups->create();
        $backups->verify($manifest['id']);
        $backups->export($manifest['id']);
        $backups->push($manifest['id']);
        $backups->drill($manifest['id']);

        $report = $status->report();

        self::assertSame('pass', $report['status']);
        self::assertSame(1, $report['counts']['local']);
        self::assertSame(1, $report['counts']['remote']);
        self::assertSame($manifest['id'], $report['latest']['local_backup']['backup_id']);
        self::assertSame($manifest['id'], $report['latest']['verified_backup']['backup_id']);
        self::assertSame($manifest['id'], $report['latest']['export']['backup_id']);
        self::assertSame($manifest['id'], $report['latest']['remote_push']['backup_id']);
        self::assertSame($manifest['id'], $report['latest']['drill']['backup_id']);
        self::assertSame('pass', $this->checkStatus($report, 'local_backup_recency'));
        self::assertSame('pass', $this->checkStatus($report, 'local_backup_verification'));
        self::assertSame('pass', $this->checkStatus($report, 'remote_backup_recency'));
        self::assertSame('pass', $this->checkStatus($report, 'backup_drill_recency'));
    }

    public function testReportFailsForStaleMissingAndUnverifiedBackups(): void
    {
        $app = $this->buildApplication();
        $backups = $app->get(BackupService::class);
        $status = $app->get(BackupStatusService::class);

        $manifest = $backups->create();
        $this->setManifestCreatedAt($manifest['path'] . '/manifest.json', '2026-03-01T00:00:00+00:00');

        $report = $status->report();

        self::assertSame('fail', $report['status']);
        self::assertSame(1, $report['counts']['local']);
        self::assertSame(0, $report['counts']['remote']);
        self::assertNull($report['latest']['export']['backup_id']);
        self::assertNull($report['latest']['remote_push']['backup_id']);
        self::assertSame('fail', $this->checkStatus($report, 'local_backup_recency'));
        self::assertSame('fail', $this->checkStatus($report, 'local_backup_verification'));
        self::assertSame('fail', $this->checkStatus($report, 'remote_backup_recency'));
        self::assertSame('fail', $this->checkStatus($report, 'backup_drill_recency'));
    }

    public function testReportTreatsRemoteReplicationAsPassWhenDisabledAndSupportsLegacyOperationLogs(): void
    {
        $app = $this->buildApplication(false);
        $backups = $app->get(BackupService::class);
        $status = $app->get(BackupStatusService::class);
        $manifest = $backups->create();

        $logFile = $app->rootPath('storage/logs/app.log');
        $legacyEntries = [
            [
                'timestamp' => date('c'),
                'level' => 'INFO',
                'channel' => 'operations',
                'request_id' => 'legacy-verify',
                'message' => 'Backup verified.',
                'context' => ['backup_id' => $manifest['id']],
            ],
            [
                'timestamp' => date('c'),
                'level' => 'INFO',
                'channel' => 'operations',
                'request_id' => 'legacy-drill',
                'message' => 'Backup drill completed.',
                'context' => ['backup_id' => $manifest['id']],
            ],
        ];

        foreach ($legacyEntries as $entry) {
            file_put_contents(
                $logFile,
                json_encode($entry, JSON_THROW_ON_ERROR) . PHP_EOL,
                FILE_APPEND
            );
        }

        $report = $status->report();

        self::assertSame('pass', $report['status']);
        self::assertSame('pass', $this->checkStatus($report, 'remote_backup_recency'));
        self::assertStringContainsString(
            'disabled',
            $this->checkMessage($report, 'remote_backup_recency')
        );
        self::assertSame($manifest['id'], $report['latest']['verified_backup']['backup_id']);
        self::assertSame($manifest['id'], $report['latest']['drill']['backup_id']);
    }

    public function testReportFailsWhenRemoteStatusCannotBeLoadedAndLocalTimestampIsInvalid(): void
    {
        $this->failRemoteList = true;
        $app = $this->buildApplication();
        $backups = $app->get(BackupService::class);
        $status = $app->get(BackupStatusService::class);

        $manifest = $backups->create();
        $backups->verify($manifest['id']);
        $this->setManifestCreatedAt($manifest['path'] . '/manifest.json', 'invalid-timestamp');

        $report = $status->report();

        self::assertSame('fail', $report['status']);
        self::assertNull($report['latest']['local_backup']['age_hours']);
        self::assertSame('fail', $this->checkStatus($report, 'local_backup_recency'));
        self::assertSame('fail', $this->checkStatus($report, 'remote_backup_recency'));
        self::assertStringContainsString(
            'could not be determined',
            $this->checkMessage($report, 'remote_backup_recency')
        );
    }

    public function testReportCoversVerificationMismatchAndStaleRemoteAndDrillBranches(): void
    {
        $app = $this->buildApplication();
        $backups = $app->get(BackupService::class);
        $status = $app->get(BackupStatusService::class);

        $first = $backups->create();
        $this->setManifestCreatedAt($first['path'] . '/manifest.json', '2026-04-01T00:00:00+00:00');
        $backups->verify($first['id']);
        $backups->export($first['id']);
        $push = $backups->push($first['id']);

        $this->remoteObjects[$push['object_key']]['headers']['x-amz-meta-created-at'] = '2026-03-01T00:00:00+00:00';

        $logFile = $app->rootPath('storage/logs/app.log');
        file_put_contents(
            $logFile,
            json_encode([
                'timestamp' => '2026-03-01T00:00:00+00:00',
                'level' => 'INFO',
                'channel' => 'operations',
                'request_id' => 'legacy-drill-stale',
                'message' => 'Backup drill completed.',
                'context' => ['backup_id' => $first['id']],
            ], JSON_THROW_ON_ERROR) . PHP_EOL,
            FILE_APPEND
        );

        $second = $backups->create();
        $report = $status->report();

        self::assertSame('fail', $report['status']);
        self::assertSame('pass', $this->checkStatus($report, 'local_backup_recency'));
        self::assertSame('fail', $this->checkStatus($report, 'local_backup_verification'));
        self::assertStringContainsString($first['id'], $this->checkMessage($report, 'local_backup_verification'));
        self::assertStringContainsString($second['id'], $this->checkMessage($report, 'local_backup_verification'));
        self::assertSame('fail', $this->checkStatus($report, 'remote_backup_recency'));
        self::assertSame('fail', $this->checkStatus($report, 'backup_drill_recency'));
    }

    public function testAgeHoursReturnsNullForMissingTimestamp(): void
    {
        $service = $this->buildApplication()->get(BackupStatusService::class);
        $ageHours = new ReflectionMethod(BackupStatusService::class, 'ageHours');
        $ageHours->setAccessible(true);

        self::assertNull($ageHours->invoke($service, null));
        self::assertNull($ageHours->invoke($service, ''));
    }

    private function buildApplication(bool $configureRemote = true): Application
    {
        $root = sys_get_temp_dir() . '/sims-backup-status-' . bin2hex(random_bytes(4));
        $this->temporaryRoots[] = $root;

        mkdir($root . '/storage/app/private/uploads/portraits', 0775, true);
        mkdir($root . '/storage/app/public/id-cards', 0775, true);
        mkdir($root . '/storage/backups', 0775, true);
        mkdir($root . '/storage/database', 0775, true);
        mkdir($root . '/storage/logs', 0775, true);

        file_put_contents($root . '/.env', "APP_ENV=production\nAPP_KEY=test\n");
        file_put_contents($root . '/storage/app/private/uploads/portraits/demo.txt', 'portrait');
        file_put_contents($root . '/storage/app/public/id-cards/card.txt', 'card');

        $databaseFile = $root . '/storage/database/app.sqlite';
        touch($databaseFile);

        $app = new Application($root);
        $app->singleton(Config::class, static fn () => new Config([
            'app' => [
                'url' => 'https://example.test',
                'env' => 'production',
                'debug' => false,
                'key' => 'testing-key',
                'version' => 'test-build',
            ],
            'db' => [
                'driver' => 'sqlite',
                'database' => $databaseFile,
            ],
            'session' => [
                'secure' => true,
                'path' => $root . '/storage/framework/sessions',
            ],
            'security' => [
                'default_password' => 'Password123!',
            ],
            'backup' => [
                'export_key' => 'test-export-key',
                'storage_path' => $root . '/storage/backups',
                'max_age_hours' => 24,
                'remote_max_age_hours' => 24,
                'drill_max_age_hours' => 168,
                'remote' => [
                    'driver' => $configureRemote ? 's3' : '',
                    'bucket' => $configureRemote ? 'test-bucket' : '',
                    'region' => $configureRemote ? 'us-east-1' : '',
                    'endpoint' => $configureRemote ? 'https://s3.example.test' : '',
                    'access_key' => $configureRemote ? 'access-key' : '',
                    'secret_key' => $configureRemote ? 'secret-key' : '',
                    'prefix' => $configureRemote ? 'exports' : '',
                    'path_style' => true,
                ],
            ],
            'notifications' => [
                'email_driver' => 'log',
                'sms_driver' => 'log',
            ],
        ]));
        $app->singleton(RequestContext::class, static fn (): RequestContext => new RequestContext());
        $app->singleton(Logger::class, static fn (Application $app): Logger => new Logger(
            $app->rootPath('storage/logs/app.log'),
            $app->get(RequestContext::class)
        ));
        $app->singleton(Database::class, static fn (Application $app): Database => new Database(
            $app->get(Config::class),
            $app->get(Logger::class)
        ));
        $app->singleton(S3BackupRemoteStore::class, fn (Application $app): S3BackupRemoteStore => new S3BackupRemoteStore(
            $app->get(Config::class),
            $this->remoteRequestHandler()
        ));
        $app->singleton(BackupService::class, static fn (Application $app): BackupService => new BackupService(
            $app->get(Database::class),
            $app->get(Config::class),
            $app->get(Logger::class),
            $app->rootPath(),
            $app->get(S3BackupRemoteStore::class)
        ));
        $app->singleton(BackupStatusService::class, static fn (Application $app): BackupStatusService => new BackupStatusService(
            $app->get(BackupService::class),
            $app->get(Config::class),
            $app->get(Logger::class),
            $app->get(RequestContext::class)
        ));

        $database = $app->get(Database::class)->connection();
        DatabaseBuilder::migrate($database, 'sqlite');
        DatabaseBuilder::seed($database, 'Password123!');

        return $app;
    }

    /**
     * @return callable(string, string, array<string, string>, string): array{status: int, headers: array<string, string>, body: string}
     */
    private function remoteRequestHandler(): callable
    {
        return function (string $method, string $url, array $headers, string $body): array {
            $parts = parse_url($url);
            self::assertIsArray($parts);

            if ($method === 'GET' && str_contains($url, 'list-type=2')) {
                if ($this->failRemoteList) {
                    return [
                        'status' => 500,
                        'headers' => [],
                        'body' => '',
                    ];
                }

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

            return [
                'status' => 405,
                'headers' => [],
                'body' => '',
            ];
        };
    }

    /**
     * @param array{checks: list<array{name: string, status: string, message: string}>} $report
     */
    private function checkStatus(array $report, string $name): string
    {
        foreach ($report['checks'] as $check) {
            if ($check['name'] === $name) {
                return $check['status'];
            }
        }

        self::fail(sprintf('Missing backup status check [%s].', $name));
    }

    /**
     * @param array{checks: list<array{name: string, status: string, message: string}>} $report
     */
    private function checkMessage(array $report, string $name): string
    {
        foreach ($report['checks'] as $check) {
            if ($check['name'] === $name) {
                return $check['message'];
            }
        }

        self::fail(sprintf('Missing backup status check [%s].', $name));
    }

    private function setManifestCreatedAt(string $manifestPath, string $createdAt): void
    {
        $payload = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $payload['created_at'] = $createdAt;

        file_put_contents(
            $manifestPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
