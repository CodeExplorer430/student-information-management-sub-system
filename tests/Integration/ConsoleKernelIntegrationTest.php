<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Console\ConsoleKernel;
use App\Core\Application;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\RequestContext;
use App\Services\BackupService;
use App\Services\BackupStatusService;
use App\Services\HealthService;
use App\Services\S3BackupRemoteStore;
use App\Support\DatabaseBuilder;
use RuntimeException;
use Tests\Support\IntegrationTestCase;

final class ConsoleKernelIntegrationTest extends IntegrationTestCase
{
    /**
     * @var list<string>
     */
    private array $temporaryRoots = [];

    /**
     * @var array<string, array{body: string, headers: array<string, string>}>
     */
    private array $remoteObjects = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryRoots as $root) {
            $this->removeDirectory($root);
        }

        $this->temporaryRoots = [];
        $this->remoteObjects = [];

        parent::tearDown();
    }

    public function testHandleReturnsHelpForMissingCommand(): void
    {
        $result = (new ConsoleKernel($this->app))->handle(null);

        self::assertSame(0, $result->exitCode());
        self::assertStringContainsString(
            'Available commands: migrate, seed, reset-db, db:summary, env:check, health:check, backup:create, backup:list, backup:status, backup:verify, backup:export, backup:import, backup:drill, backup:push, backup:remote:list, backup:pull, backup:prune, backup:remote:prune, backup:run, backup:restore, ops:alerts:check',
            $result->stdout()
        );
        self::assertSame('', $result->stderr());
    }

    public function testHandleReturnsUnknownCommandFailure(): void
    {
        $result = (new ConsoleKernel($this->app))->handle('unknown');

        self::assertSame(1, $result->exitCode());
        self::assertSame('', $result->stdout());
        self::assertSame("Unknown command: unknown\n", $result->stderr());
    }

    public function testHandleReturnsSummaryAndEnvironmentReportForHealthyApp(): void
    {
        $kernel = new ConsoleKernel($this->app);

        $summary = $kernel->handle('db:summary');
        $environment = $kernel->handle('env:check');

        self::assertSame(0, $summary->exitCode());
        self::assertStringContainsString('Users:', $summary->stdout());
        self::assertStringContainsString('Students:', $summary->stdout());

        self::assertSame(0, $environment->exitCode());
        self::assertStringContainsString('Deployment Readiness:', $environment->stdout());
        self::assertStringContainsString('Database Connectivity: ok', $environment->stdout());
    }

    public function testHandleReturnsFallbackReportsWhenDatabaseIsUnavailable(): void
    {
        $kernel = new ConsoleKernel($this->buildConsoleApplication(new class () {
            public function connection(): never
            {
                throw new RuntimeException('simulated db failure');
            }
        }));

        $summary = $kernel->handle('db:summary');
        $environment = $kernel->handle('env:check');

        self::assertSame(0, $summary->exitCode());
        self::assertStringContainsString('Connection Status: failed - simulated db failure', $summary->stdout());

        self::assertSame(0, $environment->exitCode());
        self::assertStringContainsString('Database Connectivity: failed - simulated db failure', $environment->stdout());
        self::assertStringContainsString('Schema Health: unknown', $environment->stdout());
    }

    public function testHandleReturnsCommandFailureGuidanceForMutatingCommands(): void
    {
        $kernel = new ConsoleKernel($this->buildConsoleApplication(new class () {
            public function connection(): never
            {
                throw new RuntimeException('simulated migration failure');
            }
        }));

        $migrate = $kernel->handle('migrate');
        $reset = $kernel->handle('reset-db');

        self::assertSame(1, $migrate->exitCode());
        self::assertStringContainsString('Command failed: simulated migration failure', $migrate->stderr());
        self::assertStringContainsString('rerun composer migrate', $migrate->stderr());

        self::assertSame(1, $reset->exitCode());
        self::assertStringContainsString('Command failed: simulated migration failure', $reset->stderr());
        self::assertStringContainsString('reset-db drops and rebuilds local demo data', $reset->stderr());
    }

    public function testHandleRunsSeedCommandWithoutFailureWhenDatabaseAlreadyContainsDemoData(): void
    {
        $result = (new ConsoleKernel($this->app))->handle('seed');

        self::assertSame(0, $result->exitCode());
        self::assertSame("Seed completed.\n", $result->stdout());
        self::assertSame('', $result->stderr());
    }

    public function testHandleRunsSuccessfulMigrateAndResetCommands(): void
    {
        $kernel = new ConsoleKernel($this->app);

        $migrate = $kernel->handle('migrate');
        $reset = $kernel->handle('reset-db');

        self::assertSame(0, $migrate->exitCode());
        self::assertSame("Migration completed.\n", $migrate->stdout());
        self::assertSame('', $migrate->stderr());

        self::assertSame(0, $reset->exitCode());
        self::assertSame("Database reset completed.\n", $reset->stdout());
        self::assertSame('', $reset->stderr());
    }

    public function testHandleReturnsHealthCheckInPlainTextAndJson(): void
    {
        $kernel = new ConsoleKernel($this->app);

        $plain = $kernel->handle('health:check');
        $json = $kernel->handle('health:check', ['--json']);

        self::assertSame(0, $plain->exitCode());
        self::assertStringContainsString('Health Check:', $plain->stdout());
        self::assertStringContainsString('database_connectivity: pass', $plain->stdout());
        self::assertStringContainsString('Backup Status:', $plain->stdout());
        self::assertStringContainsString('local_backup_recency: fail', $plain->stdout());

        self::assertSame(0, $json->exitCode());
        $payload = json_decode($json->stdout(), true);
        self::assertIsArray($payload);
        self::assertSame('pass', $payload['status'] ?? null);
        self::assertIsArray($payload['checks'] ?? null);
        self::assertIsArray($payload['backup'] ?? null);
        self::assertSame('fail', $payload['backup']['status'] ?? null);
    }

    public function testHandleReturnsBackupStatusInPlainTextAndJson(): void
    {
        ['app' => $app, 'root' => $root] = $this->buildBackupConsoleApplication();
        $kernel = new ConsoleKernel($app);

        $plainFailure = $kernel->handle('backup:status');
        self::assertSame(1, $plainFailure->exitCode());
        self::assertStringContainsString('Backup Status:', $plainFailure->stdout());
        self::assertStringContainsString('local_backup_recency: fail', $plainFailure->stdout());

        $usage = $kernel->handle('backup:status', ['--json', '--json']);
        self::assertSame(1, $usage->exitCode());
        self::assertSame("Usage: php bin/console backup:status [--json]\n", $usage->stderr());

        $create = $kernel->handle('backup:create');
        preg_match('/^Backup ID: (.+)$/m', $create->stdout(), $matches);
        $backupId = $matches[1] ?? '';
        self::assertNotSame('', $backupId);

        self::assertSame(0, $kernel->handle('backup:verify', [$backupId])->exitCode());
        self::assertSame(0, $kernel->handle('backup:export', [$backupId])->exitCode());
        self::assertSame(0, $kernel->handle('backup:push', [$backupId])->exitCode());
        self::assertSame(0, $kernel->handle('backup:drill', [$backupId])->exitCode());

        $plainSuccess = $kernel->handle('backup:status');
        self::assertSame(0, $plainSuccess->exitCode());
        self::assertStringContainsString('remote_backup_recency: pass', $plainSuccess->stdout());
        self::assertStringContainsString('backup_drill_recency: pass', $plainSuccess->stdout());

        $json = $kernel->handle('backup:status', ['--json']);
        self::assertSame(0, $json->exitCode());
        $payload = json_decode($json->stdout(), true, flags: JSON_THROW_ON_ERROR);
        /** @var array{
         *     status: string,
         *     latest: array{
         *         local_backup: array{backup_id: string|null},
         *         verified_backup: array{backup_id: string|null},
         *         remote_push: array{backup_id: string|null}
         *     },
         *     counts: array{local: int, remote: int}
         * } $payload
         */
        self::assertIsArray($payload);
        self::assertSame('pass', $payload['status'] ?? null);
        self::assertSame($backupId, $payload['latest']['local_backup']['backup_id'] ?? null);
        self::assertSame($backupId, $payload['latest']['verified_backup']['backup_id'] ?? null);
        self::assertSame($backupId, $payload['latest']['remote_push']['backup_id'] ?? null);
        self::assertSame(1, $payload['counts']['local'] ?? null);
        self::assertSame(1, $payload['counts']['remote'] ?? null);
        self::assertSame($root . '/storage/backups', $app->get(BackupService::class)->backupsPath());
    }

    public function testHandleReturnsOperationalAlertsInPlainTextAndJson(): void
    {
        $kernel = new ConsoleKernel($this->app);
        $plain = $kernel->handle('ops:alerts:check');

        self::assertSame(1, $plain->exitCode());
        self::assertStringContainsString('Operational Alerts:', $plain->stdout());
        self::assertStringContainsString('backup.local.stale', $plain->stdout());

        $json = $kernel->handle('ops:alerts:check', ['--json']);
        self::assertSame(1, $json->exitCode());
        $payload = json_decode($json->stdout(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        /** @var array{status: string, counts: array{notified: int}} $payload */
        self::assertSame('fail', $payload['status'] ?? null);
        self::assertSame(0, $payload['counts']['notified'] ?? null);

        $usage = $kernel->handle('ops:alerts:check', ['--json', '--json']);
        self::assertSame(1, $usage->exitCode());
        self::assertSame("Usage: php bin/console ops:alerts:check [--json]\n", $usage->stderr());
    }

    public function testHandleReturnsOperationalAlertsPassStateWhenNoAlertsAreActive(): void
    {
        $backups = $this->app->get(BackupService::class);
        $manifest = $backups->create();
        $backups->verify($manifest['id']);
        $backups->drill($manifest['id']);

        $result = (new ConsoleKernel($this->app))->handle('ops:alerts:check');

        self::assertSame(0, $result->exitCode());
        self::assertStringContainsString('Status: pass', $result->stdout());
        self::assertStringContainsString('  - none', $result->stdout());
    }

    public function testHandleRunsBackupCommandsThroughConsoleKernel(): void
    {
        ['app' => $app, 'root' => $root] = $this->buildBackupConsoleApplication();
        $kernel = new ConsoleKernel($app);

        $empty = $kernel->handle('backup:list');
        self::assertSame(0, $empty->exitCode());
        self::assertSame("No backups found.\n", $empty->stdout());

        $create = $kernel->handle('backup:create');
        self::assertSame(0, $create->exitCode());
        self::assertStringContainsString('Backup created.', $create->stdout());
        preg_match('/^Backup ID: (.+)$/m', $create->stdout(), $matches);
        $backupId = $matches[1] ?? '';
        self::assertNotSame('', $backupId);

        $verifyUsage = $kernel->handle('backup:verify');
        self::assertSame(1, $verifyUsage->exitCode());
        self::assertSame("Usage: php bin/console backup:verify <backup-id>\n", $verifyUsage->stderr());

        $verify = $kernel->handle('backup:verify', [$backupId]);
        self::assertSame(0, $verify->exitCode());
        self::assertStringContainsString('Backup verified.', $verify->stdout());
        self::assertStringContainsString('Artifacts: 3', $verify->stdout());

        file_put_contents($root . '/.env', "APP_ENV=local\n");
        @unlink($root . '/storage/app/public/id-cards/card.txt');
        $app->get(Database::class)->connection()->exec('DELETE FROM students');

        $list = $kernel->handle('backup:list');
        self::assertSame(0, $list->exitCode());
        self::assertStringContainsString($backupId, $list->stdout());

        $usage = $kernel->handle('backup:restore');
        self::assertSame(1, $usage->exitCode());
        self::assertSame("Usage: php bin/console backup:restore <backup-id>\n", $usage->stderr());

        $restore = $kernel->handle('backup:restore', [$backupId]);
        self::assertSame(0, $restore->exitCode());
        self::assertStringContainsString('Backup restored.', $restore->stdout());
        self::assertStringContainsString('APP_ENV=production', (string) file_get_contents($root . '/.env'));
        self::assertFileExists($root . '/storage/app/public/id-cards/card.txt');
        self::assertSame(1, (int) $app->get(Database::class)->query('SELECT COUNT(*) FROM students')->fetchColumn());

        $prune = $kernel->handle('backup:prune');
        self::assertSame(0, $prune->exitCode());
        self::assertStringContainsString('Backups pruned.', $prune->stdout());
        self::assertStringContainsString('Deleted backups: none', $prune->stdout());
    }

    public function testHandleRunsBackupExportImportAndDrillCommands(): void
    {
        ['app' => $app] = $this->buildBackupConsoleApplication();
        ['app' => $importApp] = $this->buildBackupConsoleApplication();
        $kernel = new ConsoleKernel($app);
        $importKernel = new ConsoleKernel($importApp);

        $create = $kernel->handle('backup:create');
        preg_match('/^Backup ID: (.+)$/m', $create->stdout(), $matches);
        $backupId = $matches[1] ?? '';
        self::assertNotSame('', $backupId);

        $exportUsage = $kernel->handle('backup:export');
        self::assertSame(1, $exportUsage->exitCode());
        self::assertSame("Usage: php bin/console backup:export <backup-id> [--passphrase=...]\n", $exportUsage->stderr());

        $exportInvalid = $kernel->handle('backup:export', [$backupId, 'extra']);
        self::assertSame(1, $exportInvalid->exitCode());
        self::assertSame("Usage: php bin/console backup:export <backup-id> [--passphrase=...]\n", $exportInvalid->stderr());

        $export = $kernel->handle('backup:export', ['--passphrase=test-export-key', $backupId]);
        self::assertSame(0, $export->exitCode());
        self::assertStringContainsString('Backup exported.', $export->stdout());
        preg_match('/^Export path: (.+)$/m', $export->stdout(), $exportMatches);
        $exportPath = $exportMatches[1] ?? '';
        self::assertFileExists($exportPath);

        $drillUsage = $kernel->handle('backup:drill');
        self::assertSame(1, $drillUsage->exitCode());
        self::assertSame("Usage: php bin/console backup:drill <backup-id>\n", $drillUsage->stderr());

        $drill = $kernel->handle('backup:drill', [$backupId]);
        self::assertSame(0, $drill->exitCode());
        self::assertStringContainsString('Backup drill completed.', $drill->stdout());

        $importUsage = $importKernel->handle('backup:import');
        self::assertSame(1, $importUsage->exitCode());
        self::assertSame("Usage: php bin/console backup:import <archive> [--passphrase=...]\n", $importUsage->stderr());

        $importInvalid = $importKernel->handle('backup:import', ['--unexpected=1']);
        self::assertSame(1, $importInvalid->exitCode());
        self::assertSame("Usage: php bin/console backup:import <archive> [--passphrase=...]\n", $importInvalid->stderr());

        $import = $importKernel->handle('backup:import', ['--passphrase=test-export-key', $exportPath]);
        self::assertSame(0, $import->exitCode());
        self::assertStringContainsString('Backup imported.', $import->stdout());
        self::assertStringContainsString($backupId, $import->stdout());

        $duplicate = $importKernel->handle('backup:import', [$exportPath]);
        self::assertSame(1, $duplicate->exitCode());
        self::assertStringContainsString('already exists in the local store', $duplicate->stderr());
    }

    public function testHandlePrunesBackupsWithKeepOverrideAndRejectsInvalidUsage(): void
    {
        ['app' => $app] = $this->buildBackupConsoleApplication();
        $kernel = new ConsoleKernel($app);

        $firstCreate = $kernel->handle('backup:create');
        preg_match('/^Backup ID: (.+)$/m', $firstCreate->stdout(), $firstMatches);
        $firstBackupId = $firstMatches[1] ?? '';
        self::assertNotSame('', $firstBackupId);
        $this->setBackupCreatedAt(
            $app->rootPath('storage/backups/' . $firstBackupId . '/manifest.json'),
            '2026-04-01T00:00:00+00:00'
        );

        $secondCreate = $kernel->handle('backup:create');
        preg_match('/^Backup ID: (.+)$/m', $secondCreate->stdout(), $secondMatches);
        $secondBackupId = $secondMatches[1] ?? '';
        self::assertNotSame('', $secondBackupId);
        $this->setBackupCreatedAt(
            $app->rootPath('storage/backups/' . $secondBackupId . '/manifest.json'),
            '2026-04-02T00:00:00+00:00'
        );

        $invalid = $kernel->handle('backup:prune', ['--keep=invalid']);
        self::assertSame(1, $invalid->exitCode());
        self::assertSame("Usage: php bin/console backup:prune [--keep=<n>]\n", $invalid->stderr());

        $tooMany = $kernel->handle('backup:prune', ['--keep=1', '--keep=2']);
        self::assertSame(1, $tooMany->exitCode());
        self::assertSame("Usage: php bin/console backup:prune [--keep=<n>]\n", $tooMany->stderr());

        $prune = $kernel->handle('backup:prune', ['--keep=1']);
        self::assertSame(0, $prune->exitCode());
        self::assertStringContainsString('Deleted backups: ' . $firstBackupId, $prune->stdout());
        self::assertDirectoryDoesNotExist($app->rootPath('storage/backups/' . $firstBackupId));
        self::assertDirectoryExists($app->rootPath('storage/backups/' . $secondBackupId));
    }

    public function testHandleRunsRemoteBackupCommandsThroughConsoleKernel(): void
    {
        ['app' => $app] = $this->buildBackupConsoleApplication();
        ['app' => $pullApp] = $this->buildBackupConsoleApplication();
        $kernel = new ConsoleKernel($app);
        $pullKernel = new ConsoleKernel($pullApp);

        $emptyRemote = $kernel->handle('backup:remote:list');
        self::assertSame(0, $emptyRemote->exitCode());
        self::assertSame("No remote backup exports found.\n", $emptyRemote->stdout());

        $create = $kernel->handle('backup:create');
        preg_match('/^Backup ID: (.+)$/m', $create->stdout(), $matches);
        $backupId = $matches[1] ?? '';
        self::assertNotSame('', $backupId);

        $pushUsage = $kernel->handle('backup:push');
        self::assertSame(1, $pushUsage->exitCode());
        self::assertSame("Usage: php bin/console backup:push <backup-id>\n", $pushUsage->stderr());

        $push = $kernel->handle('backup:push', [$backupId]);
        self::assertSame(0, $push->exitCode());
        self::assertStringContainsString('Remote backup pushed.', $push->stdout());
        preg_match('/^Object key: (.+)$/m', $push->stdout(), $pushMatches);
        $objectKey = $pushMatches[1] ?? '';
        self::assertNotSame('', $objectKey);

        $remoteList = $kernel->handle('backup:remote:list');
        self::assertSame(0, $remoteList->exitCode());
        self::assertStringContainsString($objectKey, $remoteList->stdout());
        self::assertStringContainsString($backupId, $remoteList->stdout());

        $pullUsage = $pullKernel->handle('backup:pull');
        self::assertSame(1, $pullUsage->exitCode());
        self::assertSame("Usage: php bin/console backup:pull <remote-object>\n", $pullUsage->stderr());

        $pull = $pullKernel->handle('backup:pull', [$objectKey]);
        self::assertSame(0, $pull->exitCode());
        self::assertStringContainsString('Remote backup pulled.', $pull->stdout());
        self::assertStringContainsString($backupId, $pull->stdout());

        $duplicatePull = $pullKernel->handle('backup:pull', [$objectKey]);
        self::assertSame(1, $duplicatePull->exitCode());
        self::assertStringContainsString('already exists locally', $duplicatePull->stderr());
    }

    public function testHandleRunsRemotePruneAndAutomatedBackupCommands(): void
    {
        ['app' => $app] = $this->buildBackupConsoleApplication();
        $kernel = new ConsoleKernel($app);

        $emptyRemotePrune = $kernel->handle('backup:remote:prune');
        self::assertSame(0, $emptyRemotePrune->exitCode());
        self::assertStringContainsString('Deleted remote backups: none', $emptyRemotePrune->stdout());

        $createOne = $kernel->handle('backup:create');
        preg_match('/^Backup ID: (.+)$/m', $createOne->stdout(), $createOneMatches);
        $backupOne = $createOneMatches[1] ?? '';
        self::assertNotSame('', $backupOne);
        self::assertSame(0, $kernel->handle('backup:push', [$backupOne])->exitCode());

        $createTwo = $kernel->handle('backup:create');
        preg_match('/^Backup ID: (.+)$/m', $createTwo->stdout(), $createTwoMatches);
        $backupTwo = $createTwoMatches[1] ?? '';
        self::assertNotSame('', $backupTwo);
        self::assertSame(0, $kernel->handle('backup:push', [$backupTwo])->exitCode());

        $invalidRemotePrune = $kernel->handle('backup:remote:prune', ['--keep=1', '--keep=2']);
        self::assertSame(1, $invalidRemotePrune->exitCode());
        self::assertSame("Usage: php bin/console backup:remote:prune [--keep=<n>]\n", $invalidRemotePrune->stderr());

        $remotePrune = $kernel->handle('backup:remote:prune', ['--keep=0']);
        self::assertSame(0, $remotePrune->exitCode());
        self::assertStringContainsString('Deleted remote backups:', $remotePrune->stdout());
        self::assertSame("No remote backup exports found.\n", $kernel->handle('backup:remote:list')->stdout());

        $invalidRun = $kernel->handle('backup:run', ['--remote-keep=1']);
        self::assertSame(1, $invalidRun->exitCode());
        self::assertSame(
            "Usage: php bin/console backup:run [--push-remote] [--keep=<n>] [--remote-keep=<n>] [--json]\n",
            $invalidRun->stderr()
        );

        $duplicatePushRemote = $kernel->handle('backup:run', ['--push-remote', '--push-remote']);
        self::assertSame(1, $duplicatePushRemote->exitCode());
        self::assertSame(
            "Usage: php bin/console backup:run [--push-remote] [--keep=<n>] [--remote-keep=<n>] [--json]\n",
            $duplicatePushRemote->stderr()
        );

        $duplicateJson = $kernel->handle('backup:run', ['--json', '--json']);
        self::assertSame(1, $duplicateJson->exitCode());
        self::assertSame(
            "Usage: php bin/console backup:run [--push-remote] [--keep=<n>] [--remote-keep=<n>] [--json]\n",
            $duplicateJson->stderr()
        );

        $duplicateKeep = $kernel->handle('backup:run', ['--keep=0', '--keep=1']);
        self::assertSame(1, $duplicateKeep->exitCode());
        self::assertSame(
            "Usage: php bin/console backup:run [--push-remote] [--keep=<n>] [--remote-keep=<n>] [--json]\n",
            $duplicateKeep->stderr()
        );

        $duplicateRemoteKeep = $kernel->handle('backup:run', ['--push-remote', '--remote-keep=0', '--remote-keep=1']);
        self::assertSame(1, $duplicateRemoteKeep->exitCode());
        self::assertSame(
            "Usage: php bin/console backup:run [--push-remote] [--keep=<n>] [--remote-keep=<n>] [--json]\n",
            $duplicateRemoteKeep->stderr()
        );

        $unknownOption = $kernel->handle('backup:run', ['--unexpected']);
        self::assertSame(1, $unknownOption->exitCode());
        self::assertSame(
            "Usage: php bin/console backup:run [--push-remote] [--keep=<n>] [--remote-keep=<n>] [--json]\n",
            $unknownOption->stderr()
        );

        $localRun = $kernel->handle('backup:run', ['--keep=0', '--json']);
        self::assertSame(0, $localRun->exitCode());
        $localPayload = json_decode($localRun->stdout(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($localPayload);
        self::assertSame('pass', $localPayload['status'] ?? null);
        self::assertIsString($localPayload['backup_id'] ?? null);
        self::assertIsString($localPayload['local_export_path'] ?? null);
        self::assertSame(null, $localPayload['remote_object_key'] ?? null);
        self::assertIsArray($localPayload['deleted_local'] ?? null);
        self::assertIsArray($localPayload['steps'] ?? null);

        $remoteRun = $kernel->handle('backup:run', ['--push-remote', '--keep=0', '--remote-keep=0']);
        self::assertSame(0, $remoteRun->exitCode());
        self::assertStringContainsString('Automated backup completed.', $remoteRun->stdout());
        self::assertStringContainsString('Remote object key:', $remoteRun->stdout());
        self::assertStringContainsString('prune_remote: pass', $remoteRun->stdout());
        self::assertSame("No remote backup exports found.\n", $kernel->handle('backup:remote:list')->stdout());
    }

    public function testHandleReturnsJsonFailureForAutomatedBackupRun(): void
    {
        ['app' => $app] = $this->buildBackupConsoleApplication(false);
        $kernel = new ConsoleKernel($app);

        $result = $kernel->handle('backup:run', ['--push-remote', '--json']);

        self::assertSame(1, $result->exitCode());
        $payload = json_decode($result->stdout(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame('fail', $payload['status'] ?? null);
        self::assertSame('push', $payload['stage'] ?? null);
        self::assertIsString($payload['backup_id'] ?? null);
        self::assertStringContainsString('Remote backup storage is not configured.', string_value($payload['message'] ?? ''));
        self::assertIsArray($payload['steps'] ?? null);
    }

    public function testHandleReturnsPlainTextFailureForAutomatedBackupRun(): void
    {
        ['app' => $app] = $this->buildBackupConsoleApplication(false);
        $kernel = new ConsoleKernel($app);

        $result = $kernel->handle('backup:run', ['--push-remote']);

        self::assertSame(1, $result->exitCode());
        self::assertSame('', $result->stdout());
        self::assertStringContainsString('Automated backup failed.', $result->stderr());
        self::assertStringContainsString('Stage: push', $result->stderr());
        self::assertStringContainsString('Message: Remote backup storage is not configured.', $result->stderr());
        self::assertMatchesRegularExpression('/Request ID: [a-f0-9]{16}/', $result->stderr());
    }

    public function testReleaseCheckScriptVerifiesBackupBeforeMigration(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/scripts/release-check.sh');

        self::assertIsString($script);
        self::assertStringContainsString('STEP="backup:verify"', $script);
        self::assertStringContainsString('php "${ROOT_DIR}/bin/console" backup:verify "${BACKUP_ID}"', $script);
        self::assertStringContainsString('if [[ "${PUSH_REMOTE}" == true ]]; then', $script);
        self::assertStringContainsString('STEP="backup:push"', $script);
        self::assertStringContainsString('php "${ROOT_DIR}/bin/console" backup:push "${BACKUP_ID}"', $script);
        self::assertStringContainsString('--smoke-url=', $script);
        self::assertStringContainsString('STEP="deployment-smoke"', $script);
        self::assertStringContainsString('scripts/deployment-smoke.sh', $script);
        self::assertStringContainsString('release.failed', $script);
        self::assertStringContainsString('release.completed', $script);
        self::assertStringContainsString('Scheduled backups should use php bin/console backup:run', $script);
        self::assertLessThan(
            strpos($script, 'STEP="migrate"'),
            strpos($script, 'STEP="backup:verify"')
        );
        self::assertLessThan(
            strpos($script, 'STEP="backup:push"'),
            strpos($script, 'STEP="backup:export"')
        );
    }

    public function testDeploymentSmokeScriptLogsStableOperationsEvents(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/scripts/deployment-smoke.sh');

        self::assertIsString($script);
        self::assertStringContainsString('deployment.smoke.failed', $script);
        self::assertStringContainsString('deployment.smoke.completed', $script);
        self::assertStringContainsString('trap \'log_failure\' ERR', $script);
    }

    private function buildConsoleApplication(object $database): Application
    {
        $app = new Application(dirname(__DIR__, 2));
        $backupStoragePath = sys_get_temp_dir() . '/sims-console-status-' . bin2hex(random_bytes(4));
        $this->temporaryRoots[] = $backupStoragePath;
        $app->singleton(Config::class, fn (): Config => new Config([
            'app' => [
                'url' => 'http://127.0.0.1:8000',
                'env' => 'testing',
                'debug' => false,
                'key' => 'testing-key',
                'version' => 'test-build',
            ],
            'db' => [
                'driver' => 'sqlite',
                'database' => 'broken.sqlite',
            ],
            'session' => [
                'secure' => false,
                'path' => dirname(__DIR__, 2) . '/storage/framework/sessions',
            ],
            'security' => [
                'default_password' => 'Password123!',
            ],
            'backup' => [
                'export_key' => 'test-export-key',
                'storage_path' => $backupStoragePath,
                'max_age_hours' => 24,
                'remote_max_age_hours' => 24,
                'drill_max_age_hours' => 168,
                'remote' => [
                    'driver' => 's3',
                    'bucket' => 'test-bucket',
                    'region' => 'us-east-1',
                    'endpoint' => 'https://s3.example.test',
                    'access_key' => 'access-key',
                    'secret_key' => 'secret-key',
                    'prefix' => 'exports',
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
            $app->rootPath('storage/logs/test-console.log'),
            $app->get(RequestContext::class)
        ));
        $app->singleton(Database::class, static fn () => $database);
        $app->singleton(HealthService::class, static fn (Application $app): HealthService => new HealthService(
            $app->get(Database::class),
            $app->get(Config::class),
            $app->get(RequestContext::class),
            $app->rootPath()
        ));
        $app->singleton(BackupStatusService::class, static fn (Application $app): BackupStatusService => new BackupStatusService(
            $app->get(BackupService::class),
            $app->get(Config::class),
            $app->get(Logger::class),
            $app->get(RequestContext::class)
        ));

        return $app;
    }

    private function setBackupCreatedAt(string $manifestPath, string $createdAt): void
    {
        $payload = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $payload['created_at'] = $createdAt;

        file_put_contents(
            $manifestPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
        );
    }

    /**
     * @return array{app: Application, root: string}
     */
    private function buildBackupConsoleApplication(bool $configureRemote = true): array
    {
        $root = sys_get_temp_dir() . '/sims-console-backup-' . bin2hex(random_bytes(4));
        $this->temporaryRoots[] = $root;

        mkdir($root . '/storage/app/private/uploads/portraits', 0775, true);
        mkdir($root . '/storage/app/public/id-cards', 0775, true);
        mkdir($root . '/storage/backups', 0775, true);
        mkdir($root . '/storage/database', 0775, true);
        mkdir($root . '/storage/framework/sessions', 0775, true);
        mkdir($root . '/storage/logs', 0775, true);

        file_put_contents($root . '/.env', "APP_ENV=production\nAPP_KEY=test\n");
        file_put_contents($root . '/storage/app/private/uploads/portraits/demo.txt', 'portrait');
        file_put_contents($root . '/storage/app/public/id-cards/card.txt', 'card');

        $databaseFile = $root . '/storage/database/app.sqlite';
        touch($databaseFile);

        $app = new Application($root);
        $app->singleton(Config::class, static fn () => new Config([
            'app' => [
                'url' => 'http://127.0.0.1:8000',
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
                'secure' => false,
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
        $app->singleton(HealthService::class, static fn (Application $app): HealthService => new HealthService(
            $app->get(Database::class),
            $app->get(Config::class),
            $app->get(RequestContext::class),
            $app->rootPath()
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
        $database->exec(
            "INSERT INTO users (id, name, email, password_hash, role, department, created_at, updated_at)
             VALUES (1, 'Admin', 'admin@example.test', 'hash', 'admin', 'Registrar', '2026-04-01 00:00:00', '2026-04-01 00:00:00')"
        );
        $database->exec(
            "INSERT INTO students (
                id, student_number, first_name, middle_name, last_name, birthdate, program, year_level,
                email, phone, address, guardian_name, guardian_contact, department, enrollment_status, photo_path, created_at, updated_at
             ) VALUES (
                1, 'ST-2026-1001', 'Leah', '', 'Ramos', '2000-01-01', 'BSCS', '3',
                'student@example.test', '09123456789', 'Manila', 'Maria Ramos', '09112223333', 'Computing',
                'Active', 'portraits/demo.txt', '2026-04-01 00:00:00', '2026-04-01 00:00:00'
             )"
        );
        $database->exec(
            "INSERT INTO id_cards (student_id, file_path, qr_payload, barcode_payload, generated_by, generated_at)
             VALUES (1, 'card.txt', 'qr-data', 'barcode-data', 1, '2026-04-01 00:00:00')"
        );

        return [
            'app' => $app,
            'root' => $root,
        ];
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
