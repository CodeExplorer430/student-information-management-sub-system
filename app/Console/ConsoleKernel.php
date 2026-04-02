<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Application;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\RequestContext;
use App\Services\BackupService;
use App\Services\BackupStatusService;
use App\Services\HealthService;
use App\Services\OpsAlertService;
use App\Support\DatabaseBuilder;
use Throwable;

final class ConsoleKernel
{
    public function __construct(
        private readonly Application $app
    ) {
    }

    /**
     * @param list<string> $arguments
     */
    public function handle(?string $command, array $arguments = []): ConsoleResult
    {
        $requestId = $this->app->get(RequestContext::class)->startConsole();
        $logger = $this->app->get(Logger::class);
        $logger->info('Console command started.', [
            'command' => $command,
            'arguments' => $arguments,
        ], 'console');

        if ($command === null) {
            return new ConsoleResult(
                0,
                "Available commands: migrate, seed, reset-db, db:summary, env:check, health:check, backup:create, backup:list, backup:status, backup:verify, backup:export, backup:import, backup:drill, backup:push, backup:remote:list, backup:pull, backup:prune, backup:remote:prune, backup:run, backup:restore, ops:alerts:check\n"
            );
        }

        $config = $this->app->get(Config::class);
        $driver = string_value($config->get('db.driver', 'mysql'), 'mysql');
        $databaseName = string_value($config->get('db.database', ''), '');

        try {
            return match ($command) {
                'migrate' => $this->migrate($driver),
                'seed' => $this->seed(string_value($config->get('security.default_password', 'Password123!'), 'Password123!')),
                'reset-db' => $this->reset($driver, string_value($config->get('security.default_password', 'Password123!'), 'Password123!')),
                'db:summary' => $this->summary($driver, $databaseName),
                'env:check' => $this->environmentReport($config),
                'health:check' => $this->healthCheck($arguments),
                'backup:create' => $this->backupCreate(),
                'backup:list' => $this->backupList(),
                'backup:status' => $this->backupStatus($arguments),
                'backup:verify' => $this->backupVerify($arguments),
                'backup:export' => $this->backupExport($arguments),
                'backup:import' => $this->backupImport($arguments),
                'backup:drill' => $this->backupDrill($arguments),
                'backup:push' => $this->backupPush($arguments),
                'backup:remote:list' => $this->backupRemoteList(),
                'backup:pull' => $this->backupPull($arguments),
                'backup:prune' => $this->backupPrune($arguments),
                'backup:remote:prune' => $this->backupRemotePrune($arguments),
                'backup:run' => $this->backupRun($arguments),
                'backup:restore' => $this->backupRestore($arguments),
                'ops:alerts:check' => $this->opsAlertsCheck($arguments),
                default => new ConsoleResult(1, '', "Unknown command: {$command}\n"),
            };
        } catch (Throwable $exception) {
            $logger->error('Console command failed.', [
                'command' => $command,
                'arguments' => $arguments,
                'request_id' => $requestId,
                'message' => $exception->getMessage(),
            ], 'console');
            $stderr = "Command failed: {$exception->getMessage()}\n";

            if ($command === 'migrate') {
                $stderr .= "If the database is older than the current codebase, rerun composer migrate after verifying your MySQL/MariaDB service and credentials.\n";
            } elseif ($command === 'reset-db') {
                $stderr .= "reset-db drops and rebuilds local demo data; verify your MySQL/MariaDB service and credentials, then rerun the command.\n";
            }

            return new ConsoleResult(1, '', $stderr);
        }
    }

    private function migrate(string $driver): ConsoleResult
    {
        $this->app->get(Logger::class)->info('Migration started.', [
            'driver' => $driver,
        ], 'operations');
        $database = $this->app->get(Database::class)->connection();
        DatabaseBuilder::migrate($database, $driver);
        $this->app->get(Logger::class)->info('Migration completed.', [
            'driver' => $driver,
        ], 'operations');

        return new ConsoleResult(0, "Migration completed.\n");
    }

    private function seed(string $password): ConsoleResult
    {
        $database = $this->app->get(Database::class)->connection();
        DatabaseBuilder::seed($database, $password);

        return new ConsoleResult(0, "Seed completed.\n");
    }

    private function reset(string $driver, string $password): ConsoleResult
    {
        $this->app->get(Logger::class)->info('Database reset started.', [
            'driver' => $driver,
        ], 'operations');
        $database = $this->app->get(Database::class)->connection();
        DatabaseBuilder::reset($database, $driver);
        DatabaseBuilder::seed($database, $password);
        $this->app->get(Logger::class)->info('Database reset completed.', [
            'driver' => $driver,
        ], 'operations');

        return new ConsoleResult(0, "Database reset completed.\n");
    }

    private function summary(string $driver, string $databaseName): ConsoleResult
    {
        try {
            $database = $this->app->get(Database::class)->connection();

            return new ConsoleResult(0, DatabaseBuilder::summary($database, $driver, $databaseName));
        } catch (Throwable $exception) {
            return new ConsoleResult(0, implode(PHP_EOL, [
                sprintf('Driver: %s', $driver),
                sprintf('Database: %s', $databaseName),
                'Schema Health: unknown',
                'Connection Status: failed - ' . $exception->getMessage(),
                'Required action: check MySQL service and .env credentials, then rerun composer migrate or composer reset-db once connectivity is restored.',
                '',
            ]));
        }
    }

    private function environmentReport(Config $config): ConsoleResult
    {
        try {
            $database = $this->app->get(Database::class)->connection();

            return new ConsoleResult(0, DatabaseBuilder::environmentReport(
                $database,
                $config,
                $this->app->rootPath()
            ));
        } catch (Throwable $exception) {
            return new ConsoleResult(0, DatabaseBuilder::environmentFailureReport(
                $config,
                $this->app->rootPath(),
                'failed - ' . $exception->getMessage()
            ));
        }
    }

    /**
     * @param list<string> $arguments
     */
    private function healthCheck(array $arguments): ConsoleResult
    {
        $report = $this->app->get(HealthService::class)->ready();
        /** @var HealthReport $report */
        $report = $report;
        $backup = $this->app->get(BackupStatusService::class)->report();
        $useJson = in_array('--json', $arguments, true);

        if ($useJson) {
            return new ConsoleResult(0, json_encode([
                ...$report,
                'backup' => $backup,
            ], JSON_THROW_ON_ERROR) . PHP_EOL);
        }

        $lines = [
            'Health Check:',
            'Status: ' . $report['status'],
            'Request ID: ' . $report['request_id'],
        ];

        foreach ($report['checks'] as $check) {
            $lines[] = sprintf(
                '  - %s: %s - %s',
                $check['name'],
                $check['status'],
                $check['message']
            );
        }

        $lines[] = '';
        $lines[] = 'Backup Status:';
        $lines[] = 'Status: ' . $backup['status'];

        foreach ($backup['checks'] as $check) {
            $lines[] = sprintf(
                '  - %s: %s - %s',
                $check['name'],
                $check['status'],
                $check['message']
            );
        }

        return new ConsoleResult(0, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    private function backupCreate(): ConsoleResult
    {
        $manifest = $this->app->get(BackupService::class)->create();

        return new ConsoleResult(0, implode(PHP_EOL, [
            'Backup created.',
            'Backup ID: ' . $manifest['id'],
            'Location: ' . $manifest['path'],
            'Database dump: ' . $manifest['database']['dump_path'],
            '',
        ]));
    }

    private function backupList(): ConsoleResult
    {
        $backups = $this->app->get(BackupService::class)->list();

        if ($backups === []) {
            return new ConsoleResult(0, "No backups found.\n");
        }

        $lines = ['Available backups:'];

        foreach ($backups as $backup) {
            $lines[] = sprintf(
                '  - %s | %s | %s',
                $backup['id'],
                $backup['created_at'],
                $backup['database']['target']
            );
        }

        return new ConsoleResult(0, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    /**
     * @param list<string> $arguments
     */
    private function backupVerify(array $arguments): ConsoleResult
    {
        $backupId = string_value($arguments[0] ?? '');

        if ($backupId === '') {
            return new ConsoleResult(1, '', "Usage: php bin/console backup:verify <backup-id>\n");
        }

        $manifest = $this->app->get(BackupService::class)->verify($backupId);

        return new ConsoleResult(0, implode(PHP_EOL, [
            'Backup verified.',
            'Backup ID: ' . $manifest['id'],
            'Artifacts: ' . (string) $manifest['artifact_count'],
            'Total bytes: ' . (string) $manifest['total_bytes'],
            'Table count: ' . (string) $manifest['table_count'],
            '',
        ]));
    }

    /**
     * @param list<string> $arguments
     */
    private function backupStatus(array $arguments): ConsoleResult
    {
        if ($arguments !== [] && $arguments !== ['--json']) {
            return new ConsoleResult(1, '', "Usage: php bin/console backup:status [--json]\n");
        }

        $report = $this->app->get(BackupStatusService::class)->report();
        $useJson = $arguments === ['--json'];
        $exitCode = $report['status'] === 'pass' ? 0 : 1;

        if ($useJson) {
            return new ConsoleResult($exitCode, json_encode($report, JSON_THROW_ON_ERROR) . PHP_EOL);
        }

        $lines = [
            'Backup Status:',
            'Status: ' . $report['status'],
            'Request ID: ' . $report['request_id'],
            sprintf(
                'Counts: local=%d remote=%d',
                $report['counts']['local'],
                $report['counts']['remote']
            ),
        ];

        foreach ($report['checks'] as $check) {
            $lines[] = sprintf(
                '  - %s: %s - %s',
                $check['name'],
                $check['status'],
                $check['message']
            );
        }

        return new ConsoleResult($exitCode, implode(PHP_EOL, [...$lines, '']));
    }

    /**
     * @param list<string> $arguments
     */
    private function backupExport(array $arguments): ConsoleResult
    {
        $parsed = $this->backupTargetAndPassphrase($arguments);

        if ($parsed === null) {
            return new ConsoleResult(1, '', "Usage: php bin/console backup:export <backup-id> [--passphrase=...]\n");
        }

        $result = $this->app->get(BackupService::class)->export($parsed['target'], $parsed['passphrase']);

        return new ConsoleResult(0, implode(PHP_EOL, [
            'Backup exported.',
            'Backup ID: ' . $result['backup_id'],
            'Export path: ' . $result['export_path'],
            'Archive checksum: ' . $result['archive_checksum'],
            'Encrypted checksum: ' . $result['encrypted_checksum'],
            '',
        ]));
    }

    /**
     * @param list<string> $arguments
     */
    private function backupImport(array $arguments): ConsoleResult
    {
        $parsed = $this->backupTargetAndPassphrase($arguments);

        if ($parsed === null) {
            return new ConsoleResult(1, '', "Usage: php bin/console backup:import <archive> [--passphrase=...]\n");
        }

        $result = $this->app->get(BackupService::class)->import($parsed['target'], $parsed['passphrase']);

        return new ConsoleResult(0, implode(PHP_EOL, [
            'Backup imported.',
            'Backup ID: ' . $result['backup_id'],
            'Location: ' . $result['path'],
            '',
        ]));
    }

    /**
     * @param list<string> $arguments
     */
    private function backupDrill(array $arguments): ConsoleResult
    {
        $backupId = string_value($arguments[0] ?? '');

        if ($backupId === '') {
            return new ConsoleResult(1, '', "Usage: php bin/console backup:drill <backup-id>\n");
        }

        $result = $this->app->get(BackupService::class)->drill($backupId);

        return new ConsoleResult(0, implode(PHP_EOL, [
            'Backup drill completed.',
            'Backup ID: ' . $result['backup_id'],
            'Artifacts: ' . (string) $result['artifact_count'],
            'Total bytes: ' . (string) $result['total_bytes'],
            'Table count: ' . (string) $result['table_count'],
            '',
        ]));
    }

    /**
     * @param list<string> $arguments
     */
    private function backupPush(array $arguments): ConsoleResult
    {
        $backupId = string_value($arguments[0] ?? '');

        if ($backupId === '') {
            return new ConsoleResult(1, '', "Usage: php bin/console backup:push <backup-id>\n");
        }

        $result = $this->app->get(BackupService::class)->push($backupId);

        return new ConsoleResult(0, implode(PHP_EOL, [
            'Remote backup pushed.',
            'Backup ID: ' . $result['backup_id'],
            'Object key: ' . $result['object_key'],
            'Export path: ' . $result['export_path'],
            '',
        ]));
    }

    private function backupRemoteList(): ConsoleResult
    {
        $objects = $this->app->get(BackupService::class)->remoteList();

        if ($objects === []) {
            return new ConsoleResult(0, "No remote backup exports found.\n");
        }

        $lines = ['Remote backup exports:'];

        foreach ($objects as $object) {
            $lines[] = sprintf(
                '  - %s | %s | %s',
                $object['object_key'],
                $object['backup_id'],
                $object['created_at']
            );
        }

        return new ConsoleResult(0, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    /**
     * @param list<string> $arguments
     */
    private function backupPull(array $arguments): ConsoleResult
    {
        $objectKey = string_value($arguments[0] ?? '');

        if ($objectKey === '') {
            return new ConsoleResult(1, '', "Usage: php bin/console backup:pull <remote-object>\n");
        }

        $result = $this->app->get(BackupService::class)->pull($objectKey);

        return new ConsoleResult(0, implode(PHP_EOL, [
            'Remote backup pulled.',
            'Backup ID: ' . $result['backup_id'],
            'Object key: ' . $result['object_key'],
            'Export path: ' . $result['export_path'],
            '',
        ]));
    }

    /**
     * @param list<string> $arguments
     */
    private function backupPrune(array $arguments): ConsoleResult
    {
        $keep = $this->backupPruneKeep($arguments);

        if ($keep === null) {
            return new ConsoleResult(1, '', "Usage: php bin/console backup:prune [--keep=<n>]\n");
        }

        $result = $this->app->get(BackupService::class)->prune($keep);
        $lines = [
            'Backups pruned.',
            'Retained backups: ' . (string) $result['retained'],
        ];

        if ($result['deleted'] === []) {
            $lines[] = 'Deleted backups: none';
        } else {
            $lines[] = 'Deleted backups: ' . implode(', ', $result['deleted']);
        }

        return new ConsoleResult(0, implode(PHP_EOL, [...$lines, '']));
    }

    /**
     * @param list<string> $arguments
     */
    private function backupRemotePrune(array $arguments): ConsoleResult
    {
        $keep = $this->backupRemotePruneKeep($arguments);

        if ($keep === null) {
            return new ConsoleResult(1, '', "Usage: php bin/console backup:remote:prune [--keep=<n>]\n");
        }

        $result = $this->app->get(BackupService::class)->remotePrune($keep);
        $lines = [
            'Remote backups pruned.',
            'Retained remote backups: ' . (string) $result['retained'],
        ];

        if ($result['deleted'] === []) {
            $lines[] = 'Deleted remote backups: none';
        } else {
            $lines[] = 'Deleted remote backups: ' . implode(', ', $result['deleted']);
        }

        return new ConsoleResult(0, implode(PHP_EOL, [...$lines, '']));
    }

    /**
     * @param list<string> $arguments
     */
    private function backupRun(array $arguments): ConsoleResult
    {
        $options = $this->backupRunOptions($arguments);

        if ($options === null) {
            return new ConsoleResult(
                1,
                '',
                "Usage: php bin/console backup:run [--push-remote] [--keep=<n>] [--remote-keep=<n>] [--json]\n"
            );
        }

        $logger = $this->app->get(Logger::class);
        $service = $this->app->get(BackupService::class);
        $requestId = $this->app->get(RequestContext::class)->requestId();
        $result = [
            'status' => 'fail',
            'backup_id' => null,
            'steps' => [],
            'local_export_path' => null,
            'remote_object_key' => null,
            'deleted_local' => [],
            'deleted_remote' => [],
            'request_id' => $requestId,
        ];
        $stage = '';
        $remoteKeep = $options['remote_keep'] ?? 10;

        try {
            $stage = 'create';
            $logger->info('Automated backup stage started.', [
                'event' => 'backup.run.stage.started',
                'stage' => $stage,
            ], 'operations');
            $manifest = $service->create();
            $result['backup_id'] = $manifest['id'];
            $result['steps'][] = ['name' => $stage, 'status' => 'pass'];

            $stage = 'verify';
            $logger->info('Automated backup stage started.', [
                'event' => 'backup.run.stage.started',
                'stage' => $stage,
                'backup_id' => $result['backup_id'],
            ], 'operations');
            $service->verify(string_value($result['backup_id']));
            $result['steps'][] = ['name' => $stage, 'status' => 'pass'];

            $stage = 'export';
            $logger->info('Automated backup stage started.', [
                'event' => 'backup.run.stage.started',
                'stage' => $stage,
                'backup_id' => $result['backup_id'],
            ], 'operations');
            $export = $service->export(string_value($result['backup_id']));
            $result['local_export_path'] = $export['export_path'];
            $result['steps'][] = ['name' => $stage, 'status' => 'pass'];

            if ($options['push_remote']) {
                $stage = 'push';
                $logger->info('Automated backup stage started.', [
                    'event' => 'backup.run.stage.started',
                    'stage' => $stage,
                    'backup_id' => $result['backup_id'],
                ], 'operations');
                $push = $service->push(string_value($result['backup_id']));
                $result['remote_object_key'] = $push['object_key'];
                $result['steps'][] = ['name' => $stage, 'status' => 'pass'];
            }

            $stage = 'prune_local';
            $logger->info('Automated backup stage started.', [
                'event' => 'backup.run.stage.started',
                'stage' => $stage,
                'backup_id' => $result['backup_id'],
                'keep' => $options['keep'],
            ], 'operations');
            $localPrune = $service->prune($options['keep']);
            $result['deleted_local'] = $localPrune['deleted'];
            $result['steps'][] = ['name' => $stage, 'status' => 'pass'];

            if ($options['push_remote']) {
                $stage = 'prune_remote';
                $logger->info('Automated backup stage started.', [
                    'event' => 'backup.run.stage.started',
                    'stage' => $stage,
                    'backup_id' => $result['backup_id'],
                    'keep' => $remoteKeep,
                ], 'operations');
                $remotePrune = $service->remotePrune($remoteKeep);
                $result['deleted_remote'] = $remotePrune['deleted'];
                $result['steps'][] = ['name' => $stage, 'status' => 'pass'];
            }

            $result['status'] = 'pass';
            $logger->info('Automated backup run completed.', [
                'event' => 'backup.run.completed',
                'backup_id' => $result['backup_id'],
                'stage' => 'complete',
                'push_remote' => $options['push_remote'],
                'deleted_local' => $result['deleted_local'],
                'deleted_remote' => $result['deleted_remote'],
            ], 'operations');

            if ($options['json']) {
                return new ConsoleResult(0, json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL);
            }

            $lines = [
                'Automated backup completed.',
                'Backup ID: ' . string_value($result['backup_id']),
                'Local export path: ' . string_value($result['local_export_path']),
                'Remote object key: ' . string_value($result['remote_object_key'], 'none'),
                'Deleted local backups: ' . ($result['deleted_local'] === [] ? 'none' : implode(', ', $result['deleted_local'])),
                'Deleted remote backups: ' . ($result['deleted_remote'] === [] ? 'none' : implode(', ', $result['deleted_remote'])),
                'Request ID: ' . $requestId,
                'Steps:',
            ];

            foreach ($result['steps'] as $step) {
                $lines[] = sprintf('  - %s: %s', $step['name'], $step['status']);
            }

            return new ConsoleResult(0, implode(PHP_EOL, [...$lines, '']));
        } catch (Throwable $exception) {
            $result['steps'][] = ['name' => $stage, 'status' => 'fail'];

            $result['stage'] = $stage;
            $result['message'] = $exception->getMessage();
            $logger->error('Automated backup run failed.', [
                'event' => 'backup.run.failed',
                'backup_id' => $result['backup_id'],
                'stage' => $stage,
                'message' => $exception->getMessage(),
            ], 'operations');

            if ($options['json']) {
                return new ConsoleResult(1, json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL);
            }

            return new ConsoleResult(
                1,
                '',
                implode(PHP_EOL, [
                    'Automated backup failed.',
                    'Stage: ' . $stage,
                    'Message: ' . $exception->getMessage(),
                    'Request ID: ' . $requestId,
                    '',
                ])
            );
        }
    }

    /**
     * @param list<string> $arguments
     * @return array{target: string, passphrase: string|null}|null
     */
    private function backupTargetAndPassphrase(array $arguments): ?array
    {
        $target = '';
        $passphrase = null;

        foreach ($arguments as $argument) {
            if (preg_match('/^--passphrase=(.+)$/', $argument, $matches) === 1) {
                $passphrase = $matches[1];
                continue;
            }

            if (str_starts_with($argument, '--') || $target !== '') {
                return null;
            }

            $target = $argument;
        }

        if ($target === '') {
            return null;
        }

        return [
            'target' => $target,
            'passphrase' => $passphrase,
        ];
    }

    /**
     * @param list<string> $arguments
     */
    private function backupPruneKeep(array $arguments): ?int
    {
        if ($arguments === []) {
            return 10;
        }

        if (count($arguments) !== 1) {
            return null;
        }

        $argument = $arguments[0];

        if (preg_match('/^--keep=(\d+)$/', $argument, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @param list<string> $arguments
     */
    private function backupRemotePruneKeep(array $arguments): ?int
    {
        return $this->backupPruneKeep($arguments);
    }

    /**
     * @param list<string> $arguments
     * @return array{push_remote: bool, keep: int, remote_keep: int|null, json: bool}|null
     */
    private function backupRunOptions(array $arguments): ?array
    {
        $pushRemote = false;
        $keep = 10;
        $remoteKeep = null;
        $useJson = false;
        $keepSet = false;
        $remoteKeepSet = false;

        foreach ($arguments as $argument) {
            if ($argument === '--push-remote') {
                if ($pushRemote) {
                    return null;
                }

                $pushRemote = true;
                continue;
            }

            if ($argument === '--json') {
                if ($useJson) {
                    return null;
                }

                $useJson = true;
                continue;
            }

            if (preg_match('/^--keep=(\d+)$/', $argument, $matches) === 1) {
                if ($keepSet) {
                    return null;
                }

                $keep = (int) $matches[1];
                $keepSet = true;
                continue;
            }

            if (preg_match('/^--remote-keep=(\d+)$/', $argument, $matches) === 1) {
                if ($remoteKeepSet) {
                    return null;
                }

                $remoteKeep = (int) $matches[1];
                $remoteKeepSet = true;
                continue;
            }

            return null;
        }

        if ($remoteKeep !== null && !$pushRemote) {
            return null;
        }

        return [
            'push_remote' => $pushRemote,
            'keep' => $keep,
            'remote_keep' => $remoteKeep,
            'json' => $useJson,
        ];
    }

    /**
     * @param list<string> $arguments
     */
    private function backupRestore(array $arguments): ConsoleResult
    {
        $backupId = string_value($arguments[0] ?? '');

        if ($backupId === '') {
            return new ConsoleResult(1, '', "Usage: php bin/console backup:restore <backup-id>\n");
        }

        $manifest = $this->app->get(BackupService::class)->restore($backupId);

        return new ConsoleResult(0, implode(PHP_EOL, [
            'Backup restored.',
            'Backup ID: ' . $manifest['id'],
            '',
        ]));
    }

    /**
     * @param list<string> $arguments
     */
    private function opsAlertsCheck(array $arguments): ConsoleResult
    {
        if ($arguments !== [] && $arguments !== ['--json']) {
            return new ConsoleResult(1, '', "Usage: php bin/console ops:alerts:check [--json]\n");
        }

        $report = $this->app->get(OpsAlertService::class)->checkAndDispatch();
        $useJson = $arguments === ['--json'];
        $exitCode = $report['status'] === 'pass' ? 0 : 1;

        if ($useJson) {
            return new ConsoleResult($exitCode, json_encode($report, JSON_THROW_ON_ERROR) . PHP_EOL);
        }

        $lines = [
            'Operational Alerts:',
            'Status: ' . $report['status'],
            'Request ID: ' . $report['request_id'],
            sprintf(
                'Counts: active=%d notified=%d resolved=%d',
                $report['counts']['active'],
                $report['counts']['notified'],
                $report['counts']['resolved']
            ),
        ];

        if ($report['active_alerts'] === []) {
            $lines[] = '  - none';
        } else {
            foreach ($report['active_alerts'] as $alert) {
                $lines[] = sprintf(
                    '  - %s: %s - %s',
                    $alert['key'],
                    $alert['severity'],
                    $alert['message']
                );
                $lines[] = '    Remediation: ' . $alert['remediation'];
            }
        }

        return new ConsoleResult($exitCode, implode(PHP_EOL, [...$lines, '']));
    }
}
