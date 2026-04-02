<?php

declare(strict_types=1);

namespace App\Services {
    /**
     * @param 0|1|2 $sortingOrder
     * @return list<string>|false
     */
    function scandir(string $directory, int $sortingOrder = SCANDIR_SORT_ASCENDING): array|false
    {
        $targets = $GLOBALS['__sims_backup_scandir_false'] ?? [];

        if (is_array($targets) && in_array($directory, $targets, true)) {
            return false;
        }

        return \scandir($directory, $sortingOrder);
    }

    function file_put_contents(string $filename, mixed $data, int $flags = 0): int|false
    {
        $targets = $GLOBALS['__sims_backup_file_put_contents_false'] ?? [];
        $suffixes = $GLOBALS['__sims_backup_file_put_contents_false_suffixes'] ?? [];

        if (is_array($targets) && in_array($filename, $targets, true)) {
            return false;
        }

        if (is_array($suffixes)) {
            foreach ($suffixes as $suffix) {
                if (is_string($suffix) && str_ends_with($filename, $suffix)) {
                    return false;
                }
            }
        }

        if (is_bool($targets) && $targets) {
            return false;
        }

        return \file_put_contents($filename, $data, $flags);
    }

    function file_get_contents(string $filename, bool $useIncludePath = false, mixed $context = null, int $offset = 0, ?int $length = null): string|false
    {
        $targets = $GLOBALS['__sims_backup_file_get_contents_false'] ?? [];
        $suffixes = $GLOBALS['__sims_backup_file_get_contents_false_suffixes'] ?? [];
        $nthTargets = $GLOBALS['__sims_backup_file_get_contents_false_on_nth'] ?? [];

        if (is_array($targets) && in_array($filename, $targets, true)) {
            return false;
        }

        if (is_array($suffixes)) {
            foreach ($suffixes as $suffix) {
                if (is_string($suffix) && str_ends_with($filename, $suffix)) {
                    return false;
                }
            }
        }

        if (is_array($nthTargets) && isset($nthTargets[$filename]) && is_int($nthTargets[$filename])) {
            $counts = $GLOBALS['__sims_backup_file_get_contents_counts'] ?? [];

            if (!is_array($counts)) {
                $counts = [];
            }

            $existingCount = $counts[$filename] ?? 0;
            $count = is_int($existingCount) ? $existingCount + 1 : 1;
            $counts[$filename] = $count;
            $GLOBALS['__sims_backup_file_get_contents_counts'] = $counts;

            if ($count === $nthTargets[$filename]) {
                return false;
            }
        }

        /** @var resource|null $streamContext */
        $streamContext = is_resource($context) ? $context : null;

        if ($length !== null && $length >= 0) {
            return \file_get_contents($filename, $useIncludePath, $streamContext, $offset, $length);
        }

        return \file_get_contents($filename, $useIncludePath, $streamContext, $offset);
    }

    function copy(string $from, string $to): bool
    {
        $targets = $GLOBALS['__sims_backup_copy_false'] ?? [];
        $markUnreadableSuffixes = $GLOBALS['__sims_backup_copy_mark_destination_read_false_suffixes'] ?? [];

        if (is_array($targets) && (in_array($from, $targets, true) || in_array($to, $targets, true))) {
            return false;
        }

        if (is_array($markUnreadableSuffixes)) {
            foreach ($markUnreadableSuffixes as $suffix) {
                if (is_string($suffix) && str_ends_with($to, $suffix)) {
                    $nthTargets = $GLOBALS['__sims_backup_file_get_contents_false_on_nth'] ?? [];
                    if (!is_array($nthTargets)) {
                        $nthTargets = [];
                    }

                    $nthTargets[$to] = 2;
                    $GLOBALS['__sims_backup_file_get_contents_false_on_nth'] = $nthTargets;
                    break;
                }
            }
        }

        return \copy($from, $to);
    }

    function unlink(string $filename): bool
    {
        $targets = $GLOBALS['__sims_backup_unlink_false'] ?? [];

        if (is_array($targets) && in_array($filename, $targets, true)) {
            return false;
        }

        return \unlink($filename);
    }

    function rmdir(string $directory): bool
    {
        $targets = $GLOBALS['__sims_backup_rmdir_false'] ?? [];

        if (is_array($targets) && in_array($directory, $targets, true)) {
            return false;
        }

        return \rmdir($directory);
    }

    function mkdir(string $directory, int $permissions = 0777, bool $recursive = false): bool
    {
        $targets = $GLOBALS['__sims_backup_mkdir_false'] ?? [];

        if (is_array($targets) && in_array($directory, $targets, true)) {
            return false;
        }

        return \mkdir($directory, $permissions, $recursive);
    }

    function is_file(string $filename): bool
    {
        $targets = $GLOBALS['__sims_backup_is_file_false'] ?? [];

        if (is_array($targets) && in_array($filename, $targets, true)) {
            return false;
        }

        return \is_file($filename);
    }

    function filesize(string $filename): int|false
    {
        $targets = $GLOBALS['__sims_backup_filesize_false'] ?? [];

        if (is_array($targets) && in_array($filename, $targets, true)) {
            return false;
        }

        return \filesize($filename);
    }

    function openssl_encrypt(
        string $data,
        string $cipherAlgo,
        string $passphrase,
        int $options = 0,
        string $iv = '',
        ?string &$tag = null,
        string $aad = '',
        int $tagLength = 16
    ): string|false {
        if (($GLOBALS['__sims_backup_openssl_encrypt_false'] ?? false) === true) {
            return false;
        }

        return \openssl_encrypt($data, $cipherAlgo, $passphrase, $options, $iv, $tag, $aad, $tagLength);
    }

    function random_bytes(int $length): string
    {
        if ($length < 1) {
            throw new \ValueError('random_bytes(): Argument #1 ($length) must be greater than 0');
        }

        $queuedValues = $GLOBALS['__sims_backup_random_bytes_values'] ?? [];

        if (is_array($queuedValues) && $queuedValues !== []) {
            $value = array_shift($queuedValues);
            $GLOBALS['__sims_backup_random_bytes_values'] = $queuedValues;

            if (is_string($value) && strlen($value) === $length) {
                return $value;
            }
        }

        return \random_bytes($length);
    }
}

namespace Tests\Integration {

    use App\Core\Config;
    use App\Core\Database;
    use App\Core\Logger;
    use App\Core\RequestContext;
    use App\Services\BackupService;
    use App\Services\S3BackupRemoteStore;
    use App\Support\DatabaseBuilder;
    use PDO;
    use PDOStatement;
    use PHPUnit\Framework\TestCase;
    use ReflectionMethod;
    use RuntimeException;

    final class BackupServiceIntegrationTest extends TestCase
    {
        private string $rootPath;

        /**
         * @var list<string>
         */
        private array $temporaryRoots = [];

        protected function setUp(): void
        {
            parent::setUp();

            $this->rootPath = sys_get_temp_dir() . '/sims-backup-' . bin2hex(random_bytes(4));
            $this->temporaryRoots[] = $this->rootPath;
        }

        protected function tearDown(): void
        {
            unset(
                $GLOBALS['__sims_backup_scandir_false'],
                $GLOBALS['__sims_backup_file_put_contents_false'],
                $GLOBALS['__sims_backup_file_put_contents_false_suffixes'],
                $GLOBALS['__sims_backup_file_get_contents_false'],
                $GLOBALS['__sims_backup_file_get_contents_false_suffixes'],
                $GLOBALS['__sims_backup_file_get_contents_false_on_nth'],
                $GLOBALS['__sims_backup_file_get_contents_counts'],
                $GLOBALS['__sims_backup_copy_false'],
                $GLOBALS['__sims_backup_copy_mark_destination_read_false_suffixes'],
                $GLOBALS['__sims_backup_unlink_false'],
                $GLOBALS['__sims_backup_rmdir_false'],
                $GLOBALS['__sims_backup_mkdir_false'],
                $GLOBALS['__sims_backup_is_file_false'],
                $GLOBALS['__sims_backup_openssl_encrypt_false'],
                $GLOBALS['__sims_backup_random_bytes_values']
            );

            foreach ($this->temporaryRoots as $root) {
                if (is_dir($root)) {
                    $this->removeDirectory($root);
                }
            }

            $this->temporaryRoots = [];

            parent::tearDown();
        }

        public function testCreateListAndRestoreBackupAcrossDatabaseAndFiles(): void
        {
            ['service' => $service, 'database' => $database, 'log_file' => $logFile, 'database_file' => $databaseFile] = $this->createEnvironment();

            $manifest = $service->create();
            $verified = $service->verify($manifest['id']);
            /** @var list<array{id: string}> $backups */
            $backups = $service->list();

            self::assertCount(1, $backups);
            /** @var array{id: string} $firstBackup */
            $firstBackup = $backups[0];
            self::assertSame($manifest['id'], $firstBackup['id']);
            self::assertSame($manifest['id'], $verified['id']);
            self::assertSame('test-build', $manifest['version']);
            self::assertSame(3, $manifest['artifact_count']);
            self::assertGreaterThan(0, $manifest['total_bytes']);
            self::assertGreaterThan(0, $manifest['table_count']);
            self::assertSame(hash('sha256', (string) file_get_contents($manifest['database']['dump_path'])), $manifest['database']['checksum']);
            self::assertSame(strlen((string) file_get_contents($manifest['database']['dump_path'])), $manifest['database']['size_bytes']);
            self::assertFileExists($manifest['database']['dump_path']);
            self::assertFileExists($manifest['path'] . '/manifest.json');
            self::assertFileExists($manifest['path'] . '/files/.env');
            self::assertFileExists($manifest['path'] . '/files/private-uploads/portraits/demo.txt');
            self::assertFileExists($manifest['path'] . '/files/public-id-cards/card.txt');
            self::assertSame('.env', $manifest['artifacts'][0]['files'][0]['relative_path']);
            self::assertSame(
                hash('sha256', (string) file_get_contents($manifest['artifacts'][1]['files'][0]['path'])),
                $manifest['artifacts'][1]['files'][0]['checksum']
            );
            self::assertSame($manifest['artifact_count'], $verified['artifact_count']);
            self::assertSame($manifest['total_bytes'], $verified['total_bytes']);
            self::assertSame($manifest['table_count'], $verified['table_count']);

            file_put_contents($this->rootPath . '/.env', "APP_ENV=local\n");
            file_put_contents($this->rootPath . '/storage/app/private/uploads/extra.txt', 'remove-me');
            @unlink($this->rootPath . '/storage/app/public/id-cards/card.txt');
            $database->connection()->exec('DELETE FROM students');
            self::assertSame(0, (int) $database->query('SELECT COUNT(*) FROM students')->fetchColumn());

            $restored = $service->restore($manifest['id']);

            self::assertSame($manifest['id'], $restored['id']);
            self::assertStringContainsString('APP_ENV=production', (string) file_get_contents($this->rootPath . '/.env'));
            self::assertFileExists($this->rootPath . '/storage/app/private/uploads/portraits/demo.txt');
            self::assertFileDoesNotExist($this->rootPath . '/storage/app/private/uploads/extra.txt');
            self::assertFileExists($this->rootPath . '/storage/app/public/id-cards/card.txt');
            self::assertSame(1, (int) $database->query('SELECT COUNT(*) FROM students')->fetchColumn());
            self::assertFileExists($databaseFile);

            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);
            $entries = array_map(static function (string $line): array {
                $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                self::assertIsArray($decoded);

                return $decoded;
            }, $lines);
            self::assertContains('operations', array_column($entries, 'channel'));
            self::assertContains('Backup created.', array_column($entries, 'message'));
            self::assertContains('Backup verified.', array_column($entries, 'message'));
            self::assertContains('Backup restored.', array_column($entries, 'message'));
        }

        public function testListHandlesMissingBackupDirectoryAndSkipsInvalidManifestFiles(): void
        {
            ['service' => $service] = $this->createEnvironment();

            self::assertSame([], $service->list());

            $backupRoot = $service->backupsPath();
            mkdir($backupRoot, 0775, true);
            mkdir($backupRoot . '/broken', 0775, true);
            file_put_contents($backupRoot . '/broken/manifest.json', '{invalid-json');

            $manifest = $service->create();
            /** @var list<array{id: string}> $backups */
            $backups = $service->list();

            self::assertCount(1, $backups);
            /** @var array{id: string} $firstBackup */
            $firstBackup = $backups[0];
            self::assertSame($manifest['id'], $firstBackup['id']);
        }

        public function testRestoreRejectsMissingBackupAndMissingArtifacts(): void
        {
            ['service' => $service] = $this->createEnvironment();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Backup [missing-backup] was not found.');

            $service->restore('missing-backup');
        }

        public function testVerifyRejectsChecksumMismatchAndRestoreRefusesInvalidBackup(): void
        {
            ['service' => $service, 'database' => $database, 'log_file' => $logFile] = $this->createEnvironment();

            $manifest = $service->create();
            $manifestPath = $manifest['path'] . '/manifest.json';
            $payload = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            self::assertIsArray($payload['database'] ?? null);
            $payload['database']['checksum'] = 'invalid-checksum';
            file_put_contents(
                $manifestPath,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
            );

            try {
                $service->verify($manifest['id']);
                self::fail('Expected verify checksum failure.');
            } catch (RuntimeException $exception) {
                self::assertSame(
                    sprintf('Database dump checksum mismatch for backup [%s].', $manifest['id']),
                    $exception->getMessage()
                );
            }

            file_put_contents($this->rootPath . '/.env', "APP_ENV=local\n");
            $database->connection()->exec('DELETE FROM students');

            try {
                $service->restore($manifest['id']);
                self::fail('Expected restore verification failure.');
            } catch (RuntimeException $exception) {
                self::assertSame(
                    sprintf('Database dump checksum mismatch for backup [%s].', $manifest['id']),
                    $exception->getMessage()
                );
            }

            self::assertStringContainsString('APP_ENV=local', (string) file_get_contents($this->rootPath . '/.env'));
            self::assertSame(0, (int) $database->query('SELECT COUNT(*) FROM students')->fetchColumn());

            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);
            $entries = array_map(static function (string $line): array {
                $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                self::assertIsArray($decoded);

                return $decoded;
            }, $lines);
            self::assertContains('Backup verification failed.', array_column($entries, 'message'));
            self::assertContains('Backup restore verification failed.', array_column($entries, 'message'));
        }

        public function testPruneKeepsNewestBackupsAndSupportsKeepOverride(): void
        {
            ['service' => $service, 'log_file' => $logFile] = $this->createEnvironment();

            $first = $service->create();
            $second = $service->create();
            $third = $service->create();
            $this->setBackupCreatedAt($first['path'] . '/manifest.json', '2026-04-01T00:00:00+00:00');
            $this->setBackupCreatedAt($second['path'] . '/manifest.json', '2026-04-02T00:00:00+00:00');
            $this->setBackupCreatedAt($third['path'] . '/manifest.json', '2026-04-03T00:00:00+00:00');

            $pruned = $service->prune(2);
            self::assertSame([$first['id']], $pruned['deleted']);
            self::assertSame(2, $pruned['retained']);
            self::assertDirectoryDoesNotExist($first['path']);

            /** @var list<array{id: string}> $remaining */
            $remaining = $service->list();
            self::assertSame([$third['id'], $second['id']], array_column($remaining, 'id'));

            $finalPrune = $service->prune(1);
            self::assertSame([$second['id']], $finalPrune['deleted']);
            self::assertSame(1, $finalPrune['retained']);

            try {
                $service->prune(-1);
                self::fail('Expected invalid retention failure.');
            } catch (RuntimeException $exception) {
                self::assertSame('Backup retention keep count must be zero or greater.', $exception->getMessage());
            }

            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);
            $entries = array_map(static function (string $line): array {
                $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                self::assertIsArray($decoded);

                return $decoded;
            }, $lines);
            self::assertContains('Backups pruned.', array_column($entries, 'message'));
        }

        public function testExportImportAndDrillSupportOffHostRecoveryFlow(): void
        {
            ['service' => $service] = $this->createEnvironment();
            $manifest = $service->create();

            $export = $service->export($manifest['id']);
            self::assertSame($manifest['id'], $export['backup_id']);
            self::assertFileExists($export['export_path']);
            self::assertStringEndsWith('.tar.gz.enc', $export['export_path']);
            self::assertSame(
                hash('sha256', (string) file_get_contents($export['export_path'])),
                $export['encrypted_checksum']
            );

            $drill = $service->drill($manifest['id']);
            self::assertSame($manifest['id'], $drill['backup_id']);
            self::assertSame($manifest['artifact_count'], $drill['artifact_count']);
            self::assertSame($manifest['table_count'], $drill['table_count']);

            $importRoot = sys_get_temp_dir() . '/sims-backup-import-' . bin2hex(random_bytes(4));
            $this->temporaryRoots[] = $importRoot;
            ['service' => $importService] = $this->createEnvironmentAt($importRoot, 'test-export-key');

            $import = $importService->import($export['export_path']);
            self::assertSame($manifest['id'], $import['backup_id']);
            self::assertFileExists($import['path'] . '/manifest.json');

            $importedManifest = $importService->verify($manifest['id']);
            self::assertSame($import['path'], $importedManifest['path']);
            self::assertSame($importRoot . '/.env', $importedManifest['artifacts'][0]['source']);
            self::assertSame(
                $importRoot . '/storage/app/private/uploads',
                $importedManifest['artifacts'][1]['source']
            );
            self::assertSame(
                $importRoot . '/storage/app/public/id-cards',
                $importedManifest['artifacts'][2]['source']
            );
        }

        public function testExportImportAndDrillRejectTamperedOrInvalidInputs(): void
        {
            ['service' => $service, 'log_file' => $logFile] = $this->createEnvironment();
            $manifest = $service->create();

            $missingKeyRoot = sys_get_temp_dir() . '/sims-backup-no-key-' . bin2hex(random_bytes(4));
            $this->temporaryRoots[] = $missingKeyRoot;
            ['service' => $missingKeyService] = $this->createEnvironmentAt($missingKeyRoot, '');
            $missingKeyManifest = $missingKeyService->create();

            try {
                $missingKeyService->export($missingKeyManifest['id']);
                self::fail('Expected missing export key configuration failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Backup export key is not configured.', $exception->getMessage());
            }

            $export = $service->export($manifest['id']);
            $exportPayload = json_decode((string) file_get_contents($export['export_path']), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($exportPayload);
            $exportPayload['ciphertext'] = base64_encode('tampered');
            file_put_contents(
                $export['export_path'],
                json_encode($exportPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
            );

            try {
                $service->import($export['export_path']);
                self::fail('Expected tampered export import failure.');
            } catch (RuntimeException $exception) {
                self::assertTrue(
                    str_contains($exception->getMessage(), 'Unable to decrypt backup export')
                    || str_contains($exception->getMessage(), 'Backup export checksum mismatch')
                );
            }

            $wrongPassphraseExport = $service->export($manifest['id']);

            try {
                $service->import($wrongPassphraseExport['export_path'], 'other-key');
                self::fail('Expected wrong passphrase import failure.');
            } catch (RuntimeException $exception) {
                self::assertTrue(
                    str_contains($exception->getMessage(), 'Unable to decrypt backup export')
                    || str_contains($exception->getMessage(), 'Backup export checksum mismatch')
                );
            }

            $brokenExportPath = $this->rootPath . '/broken-export.enc';
            file_put_contents($brokenExportPath, '{invalid-json');

            try {
                $service->import($brokenExportPath);
                self::fail('Expected invalid export metadata failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Backup export [' . $brokenExportPath . '] is invalid.', $exception->getMessage());
            }

            $brokenBackupId = $service->create();
            $validExport = $service->export($brokenBackupId['id']);
            @unlink($brokenBackupId['path'] . '/manifest.json');

            try {
                $service->drill($brokenBackupId['id']);
                self::fail('Expected drill verification failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Backup manifest [', $exception->getMessage());
            }

            $duplicateRoot = sys_get_temp_dir() . '/sims-backup-duplicate-' . bin2hex(random_bytes(4));
            $this->temporaryRoots[] = $duplicateRoot;
            ['service' => $duplicateService] = $this->createEnvironmentAt($duplicateRoot);
            $duplicateImported = $duplicateService->import($validExport['export_path']);
            self::assertSame($brokenBackupId['id'], $duplicateImported['backup_id']);

            try {
                $duplicateService->import($validExport['export_path']);
                self::fail('Expected duplicate import failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('already exists in the local store', $exception->getMessage());
            }

            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);
            $entries = array_map(static function (string $line): array {
                $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                self::assertIsArray($decoded);

                return $decoded;
            }, $lines);
            self::assertContains('Backup export completed.', array_column($entries, 'message'));
            self::assertContains('Backup import failed.', array_column($entries, 'message'));
            self::assertContains('Backup drill failed.', array_column($entries, 'message'));
        }

        public function testPushRemoteListAndPullSupportOffHostRecoveryFlow(): void
        {
            $remoteObjects = [];
            ['service' => $service] = $this->createEnvironmentAt(
                $this->rootPath,
                'test-export-key',
                $this->remoteRequestHandler($remoteObjects)
            );
            $manifest = $service->create();
            $export = $service->export($manifest['id']);

            self::assertCount(1, glob($service->exportsPath() . '/*.enc') ?: []);

            $pushed = $service->push($manifest['id']);
            self::assertSame($manifest['id'], $pushed['backup_id']);
            self::assertSame($export['export_path'], $pushed['export_path']);
            self::assertCount(1, glob($service->exportsPath() . '/*.enc') ?: []);

            $secondManifest = $service->create();
            $secondPush = $service->push($secondManifest['id']);
            self::assertSame($secondManifest['id'], $secondPush['backup_id']);
            self::assertCount(2, $service->remoteList());

            $pullRoot = sys_get_temp_dir() . '/sims-backup-pull-' . bin2hex(random_bytes(4));
            $this->temporaryRoots[] = $pullRoot;
            ['service' => $pullService] = $this->createEnvironmentAt(
                $pullRoot,
                'test-export-key',
                $this->remoteRequestHandler($remoteObjects)
            );

            $pulled = $pullService->pull($pushed['object_key']);
            self::assertSame($manifest['id'], $pulled['backup_id']);
            self::assertFileExists($pulled['export_path']);
            self::assertSame(
                hash('sha256', (string) file_get_contents($pulled['export_path'])),
                $pulled['encrypted_checksum']
            );

            $imported = $pullService->import($pulled['export_path']);
            self::assertSame($manifest['id'], $imported['backup_id']);
        }

        public function testPushPullAndRemoteListRejectInvalidRemoteInputs(): void
        {
            ['service' => $unconfiguredService] = $this->createEnvironmentAt($this->rootPath, 'test-export-key', null, false);
            $manifest = $unconfiguredService->create();

            try {
                $unconfiguredService->push($manifest['id']);
                self::fail('Expected remote configuration failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Remote backup storage is not configured.', $exception->getMessage());
            }

            $remoteObjects = [];
            $configuredRoot = sys_get_temp_dir() . '/sims-backup-remote-' . bin2hex(random_bytes(4));
            $this->temporaryRoots[] = $configuredRoot;
            ['service' => $service] = $this->createEnvironmentAt(
                $configuredRoot,
                'test-export-key',
                $this->remoteRequestHandler($remoteObjects)
            );
            $manifest = $service->create();
            $pushed = $service->push($manifest['id']);

            $object = $remoteObjects[$pushed['object_key']] ?? null;
            self::assertIsArray($object);
            /** @var array{body: string, headers: array<string, string>} $object */
            $object['headers']['x-amz-meta-encrypted-checksum'] = 'mismatch';
            $remoteObjects[$pushed['object_key']] = $object;

            $pullRoot = sys_get_temp_dir() . '/sims-backup-pull-mismatch-' . bin2hex(random_bytes(4));
            $this->temporaryRoots[] = $pullRoot;
            ['service' => $pullService] = $this->createEnvironmentAt(
                $pullRoot,
                'test-export-key',
                $this->remoteRequestHandler($remoteObjects)
            );

            try {
                $pullService->pull($pushed['object_key']);
                self::fail('Expected pulled checksum mismatch failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Downloaded backup export checksum mismatch', $exception->getMessage());
            }

            self::assertFileDoesNotExist($pullService->exportsPath() . '/' . basename($pushed['object_key']));

            unset($remoteObjects[$pushed['object_key']]['headers']['x-amz-meta-backup-id']);

            try {
                $service->remoteList();
                self::fail('Expected remote metadata failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('missing required metadata', $exception->getMessage());
            }
        }

        public function testRemotePruneDeletesOlderRemoteExportsAndRejectsInvalidRetention(): void
        {
            $remoteObjects = [
                'exports/old.enc' => [
                    'body' => 'old',
                    'headers' => [
                        'content-length' => '3',
                        'x-amz-meta-backup-id' => 'old-backup',
                        'x-amz-meta-archive-checksum' => 'old-archive',
                        'x-amz-meta-encrypted-checksum' => hash('sha256', 'old'),
                        'x-amz-meta-created-at' => '2026-04-01T00:00:00+00:00',
                        'x-amz-meta-manifest-version' => 'test-build',
                    ],
                ],
                'exports/new.enc' => [
                    'body' => 'new',
                    'headers' => [
                        'content-length' => '3',
                        'x-amz-meta-backup-id' => 'new-backup',
                        'x-amz-meta-archive-checksum' => 'new-archive',
                        'x-amz-meta-encrypted-checksum' => hash('sha256', 'new'),
                        'x-amz-meta-created-at' => '2026-04-02T00:00:00+00:00',
                        'x-amz-meta-manifest-version' => 'test-build',
                    ],
                ],
            ];
            ['service' => $service, 'log_file' => $logFile] = $this->createEnvironmentAt(
                $this->rootPath,
                'test-export-key',
                $this->remoteRequestHandler($remoteObjects)
            );

            $pruned = $service->remotePrune(1);
            self::assertSame(['exports/old.enc'], $pruned['deleted']);
            self::assertSame(1, $pruned['retained']);
            self::assertArrayNotHasKey('exports/old.enc', $remoteObjects);
            self::assertArrayHasKey('exports/new.enc', $remoteObjects);

            try {
                $service->remotePrune(-1);
                self::fail('Expected invalid remote retention failure.');
            } catch (RuntimeException $exception) {
                self::assertSame('Remote backup retention keep count must be zero or greater.', $exception->getMessage());
            }

            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);
            $entries = array_map(static function (string $line): array {
                $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                self::assertIsArray($decoded);

                return $decoded;
            }, $lines);
            self::assertContains('Remote backups pruned.', array_column($entries, 'message'));
        }

        public function testRemoteBackupHelpersCoverRemainingFailureBranches(): void
        {
            $remoteObjects = [];
            ['service' => $service] = $this->createEnvironmentAt(
                $this->rootPath,
                'test-export-key',
                $this->remoteRequestHandler($remoteObjects)
            );
            $manifest = $service->create();

            try {
                $service->pull('');
                self::fail('Expected empty remote object key failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Remote backup object key is required.', $exception->getMessage());
            }

            $pushed = $service->push($manifest['id']);
            $pullRoot = sys_get_temp_dir() . '/sims-backup-pull-read-failure-' . bin2hex(random_bytes(4));
            $this->temporaryRoots[] = $pullRoot;
            ['service' => $pullService] = $this->createEnvironmentAt(
                $pullRoot,
                'test-export-key',
                $this->remoteRequestHandler($remoteObjects)
            );
            $downloadPath = $pullService->exportsPath() . '/' . basename($pushed['object_key']);
            $GLOBALS['__sims_backup_file_get_contents_false'] = [$downloadPath];

            try {
                $pullService->pull($pushed['object_key']);
                self::fail('Expected downloaded export read failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Unable to read downloaded backup export', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_file_get_contents_false']);
            }

            $latestExportResult = new ReflectionMethod(BackupService::class, 'latestExportResult');
            $latestExportResult->setAccessible(true);
            $exportResultFromPath = new ReflectionMethod(BackupService::class, 'exportResultFromPath');
            $exportResultFromPath->setAccessible(true);
            $exportCreatedAt = new ReflectionMethod(BackupService::class, 'exportCreatedAt');
            $exportCreatedAt->setAccessible(true);

            $GLOBALS['__sims_backup_scandir_false'] = [$service->exportsPath()];
            self::assertNull($latestExportResult->invoke($service, $manifest['id']));
            self::assertSame([], $service->exports());
            unset($GLOBALS['__sims_backup_scandir_false']);

            $brokenExportPath = $service->exportsPath() . '/' . $manifest['id'] . '-broken.tar.gz.enc';
            file_put_contents($brokenExportPath, '{}');
            touch($brokenExportPath, time() + 10);
            $GLOBALS['__sims_backup_file_get_contents_false_on_nth'] = [
                $brokenExportPath => 1,
            ];
            /** @var array{backup_id: string}|null $latestExport */
            $latestExport = $latestExportResult->invoke($service, $manifest['id']);
            self::assertNotNull($latestExport);
            self::assertSame($manifest['id'], $latestExport['backup_id']);
            $this->clearBackupGlobal('__sims_backup_file_get_contents_false_on_nth');
            $this->clearBackupGlobal('__sims_backup_file_get_contents_counts');

            $GLOBALS['__sims_backup_file_get_contents_false_on_nth'] = [
                $brokenExportPath => 2,
            ];

            try {
                $exports = $service->exports();
                self::assertNotSame([], $exports);

                $repushed = $service->push($manifest['id']);
                self::assertSame($manifest['id'], $repushed['backup_id']);
            } finally {
                $this->clearBackupGlobal('__sims_backup_file_get_contents_false_on_nth');
                $this->clearBackupGlobal('__sims_backup_file_get_contents_counts');
            }

            $export = $service->export($manifest['id']);
            $GLOBALS['__sims_backup_file_get_contents_false_on_nth'] = [
                $export['export_path'] => 2,
            ];

            try {
                $exportResultFromPath->invoke($service, $export['export_path']);
                self::fail('Expected export result read failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to read backup export', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_file_get_contents_false_on_nth']);
            }

            $exportEnvelope = json_decode((string) file_get_contents($export['export_path']), true);
            self::assertIsArray($exportEnvelope);
            $exportEnvelope['created_at'] = '';
            file_put_contents(
                $export['export_path'],
                json_encode($exportEnvelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
            );

            try {
                $exportCreatedAt->invoke($service, $export['export_path']);
                self::fail('Expected missing export created_at failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('is missing a creation timestamp', $exception->getMessage());
            }
        }

        public function testExportImportAndDrillCoverRemainingPublicFailureBranches(): void
        {
            ['service' => $service, 'log_file' => $logFile] = $this->createEnvironment();
            $manifest = $service->create();

            try {
                $service->import($this->rootPath . '/missing-export.tar.gz.enc');
                self::fail('Expected missing export file failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('was not found', $exception->getMessage());
            }

            $GLOBALS['__sims_backup_file_get_contents_false_suffixes'] = ['.tar.gz'];

            try {
                $service->export($manifest['id']);
                self::fail('Expected export archive read failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Unable to read archive for backup', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_file_get_contents_false_suffixes']);
            }

            $GLOBALS['__sims_backup_file_put_contents_false_suffixes'] = ['.tar.gz.enc'];

            try {
                $service->export($manifest['id']);
                self::fail('Expected export write failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Unable to write backup export', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_file_put_contents_false_suffixes']);
            }

            $validExport = $service->export($manifest['id']);
            $GLOBALS['__sims_backup_file_put_contents_false_suffixes'] = ['.tar.gz'];

            try {
                $service->import($validExport['export_path']);
                self::fail('Expected archive materialization failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Unable to materialize archive for backup', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_file_put_contents_false_suffixes']);
            }

            $mismatchedExport = $service->export($manifest['id']);
            $mismatchedPayload = json_decode(
                (string) file_get_contents($mismatchedExport['export_path']),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            self::assertIsArray($mismatchedPayload);
            $mismatchedPayload['backup_id'] = 'other-backup';
            file_put_contents(
                $mismatchedExport['export_path'],
                json_encode($mismatchedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
            );

            try {
                $service->import($mismatchedExport['export_path']);
                self::fail('Expected backup ID mismatch failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('does not match extracted backup', $exception->getMessage());
            }

            $GLOBALS['__sims_backup_copy_mark_destination_read_false_suffixes'] = ['/database.sql'];

            try {
                $service->drill($manifest['id']);
                self::fail('Expected drill dump read failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Unable to read drill database dump for backup', $exception->getMessage());
            } finally {
                unset(
                    $GLOBALS['__sims_backup_file_get_contents_false'],
                    $GLOBALS['__sims_backup_file_get_contents_false_on_nth'],
                    $GLOBALS['__sims_backup_file_get_contents_counts'],
                    $GLOBALS['__sims_backup_copy_mark_destination_read_false_suffixes']
                );
            }

            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);
            $entries = array_map(static function (string $line): array {
                $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                self::assertIsArray($decoded);

                return $decoded;
            }, $lines);

            self::assertContains('Backup export failed.', array_column($entries, 'message'));
            self::assertContains('Backup import failed.', array_column($entries, 'message'));
            self::assertContains('Backup drill failed.', array_column($entries, 'message'));
        }

        public function testRestoreAndPruneCoverRemainingPublicFailureBranches(): void
        {
            ['service' => $service, 'log_file' => $logFile] = $this->createEnvironment();

            $manifest = $service->create();
            $GLOBALS['__sims_backup_copy_false'] = [$this->rootPath . '/.env'];

            try {
                $service->restore($manifest['id']);
                self::fail('Expected restore copy failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Unable to restore file artifact [.env].', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_copy_false']);
            }

            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);
            $entries = array_map(static function (string $line): array {
                $decoded = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                self::assertIsArray($decoded);

                return $decoded;
            }, $lines);
            self::assertContains('Backup restore failed.', array_column($entries, 'message'));

            $manifestPath = $manifest['path'] . '/manifest.json';
            $payload = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            $payload['id'] = 'ghost-backup';
            file_put_contents(
                $manifestPath,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
            );

            try {
                $service->prune(0);
                self::fail('Expected prune missing directory failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Backup directory [', $exception->getMessage());
            }
        }

        public function testRestoreCoversMissingDumpAndArtifactFailureBranches(): void
        {
            ['service' => $service] = $this->createEnvironment();

            $manifest = $service->create();
            @unlink($manifest['database']['dump_path']);

            try {
                $service->restore($manifest['id']);
                self::fail('Expected missing dump failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Database dump for backup', $exception->getMessage());
            }

            $manifest = $service->create();
            @unlink($manifest['path'] . '/files/.env');

            $manifestPayload = json_decode((string) file_get_contents($manifest['path'] . '/manifest.json'), true);
            self::assertIsArray($manifestPayload);
            $artifacts = $manifestPayload['artifacts'] ?? null;
            self::assertIsArray($artifacts);
            self::assertIsArray($artifacts[0] ?? null);
            self::assertSame('.env', $artifacts[0]['name'] ?? null);
            $artifacts[0]['present'] = true;
            $manifestPayload['artifacts'] = $artifacts;
            file_put_contents(
                $manifest['path'] . '/manifest.json',
                json_encode($manifestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
            );

            try {
                $service->restore($manifest['id']);
                self::fail('Expected missing file artifact failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Backup file artifact [.env] is missing.', $exception->getMessage());
            }

            $manifest = $service->create();
            $this->removeDirectory($manifest['path'] . '/files/private-uploads');

            try {
                $service->restore($manifest['id']);
                self::fail('Expected missing directory artifact failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Backup directory artifact [private_uploads] is missing.', $exception->getMessage());
            }
        }

        public function testPrivateHelpersCoverFailureAndMysqlSpecificBranches(): void
        {
            ['service' => $service] = $this->createEnvironment();

            $writeManifest = new ReflectionMethod(BackupService::class, 'writeManifest');
            $writeManifest->setAccessible(true);
            $readManifest = new ReflectionMethod(BackupService::class, 'readManifest');
            $readManifest->setAccessible(true);
            $snapshotFile = new ReflectionMethod(BackupService::class, 'snapshotFile');
            $snapshotFile->setAccessible(true);
            $restoreFileArtifact = new ReflectionMethod(BackupService::class, 'restoreFileArtifact');
            $restoreFileArtifact->setAccessible(true);
            $restoreDirectoryArtifact = new ReflectionMethod(BackupService::class, 'restoreDirectoryArtifact');
            $restoreDirectoryArtifact->setAccessible(true);
            $copyDirectory = new ReflectionMethod(BackupService::class, 'copyDirectory');
            $copyDirectory->setAccessible(true);
            $clearDirectory = new ReflectionMethod(BackupService::class, 'clearDirectory');
            $clearDirectory->setAccessible(true);
            $removeDirectory = new ReflectionMethod(BackupService::class, 'removeDirectory');
            $removeDirectory->setAccessible(true);
            $ensureDirectory = new ReflectionMethod(BackupService::class, 'ensureDirectory');
            $ensureDirectory->setAccessible(true);
            $splitDumpStatements = new ReflectionMethod(BackupService::class, 'splitDumpStatements');
            $splitDumpStatements->setAccessible(true);
            $sqlValue = new ReflectionMethod(BackupService::class, 'sqlValue');
            $sqlValue->setAccessible(true);
            $insertStatement = new ReflectionMethod(BackupService::class, 'insertStatement');
            $insertStatement->setAccessible(true);
            $quoteIdentifier = new ReflectionMethod(BackupService::class, 'quoteIdentifier');
            $quoteIdentifier->setAccessible(true);
            $tables = new ReflectionMethod(BackupService::class, 'tables');
            $tables->setAccessible(true);
            $createTableStatement = new ReflectionMethod(BackupService::class, 'createTableStatement');
            $createTableStatement->setAccessible(true);
            $driverName = new ReflectionMethod(BackupService::class, 'driverName');
            $driverName->setAccessible(true);
            $restoreDatabase = new ReflectionMethod(BackupService::class, 'restoreDatabase');
            $restoreDatabase->setAccessible(true);
            $dumpDatabase = new ReflectionMethod(BackupService::class, 'dumpDatabase');
            $dumpDatabase->setAccessible(true);
            $backupPath = new ReflectionMethod(BackupService::class, 'backupPath');
            $backupPath->setAccessible(true);
            $appVersion = new ReflectionMethod(BackupService::class, 'appVersion');
            $appVersion->setAccessible(true);

            $targetDirectory = $this->rootPath . '/write-fail';
            mkdir($targetDirectory, 0775, true);
            $GLOBALS['__sims_backup_file_put_contents_false'] = [$targetDirectory . '/manifest.json'];

            try {
                $writeManifest->invoke($service, $targetDirectory, [
                    'id' => 'broken',
                    'created_at' => date('c'),
                    'version' => null,
                    'path' => $targetDirectory,
                    'database' => [
                        'driver' => 'sqlite',
                        'target' => 'app.sqlite',
                        'dump_path' => $targetDirectory . '/database.sql',
                        'tables' => [],
                    ],
                    'artifacts' => [],
                ]);
                self::fail('Expected manifest write failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to write backup manifest.', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_file_put_contents_false']);
            }

            file_put_contents($targetDirectory . '/manifest.json', 'invalid');

            try {
                $readManifest->invoke($service, basename($targetDirectory));
                self::fail('Expected invalid manifest failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('was not found', $exception->getMessage());
            }

            $sourceFile = $this->rootPath . '/source.txt';
            file_put_contents($sourceFile, 'demo');
            $GLOBALS['__sims_backup_copy_false'] = [$sourceFile];

            try {
                $snapshotFile->invoke($service, 'demo', $sourceFile, $this->rootPath . '/copied.txt');
                self::fail('Expected file snapshot copy failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to copy file backup source', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_copy_false']);
            }

            $restoreFileArtifact->invoke($service, [
                'name' => 'skip',
                'kind' => 'file',
                'path' => $this->rootPath . '/missing.txt',
                'source' => $this->rootPath . '/target.txt',
                'present' => false,
            ]);
            self::assertFileDoesNotExist($this->rootPath . '/target.txt');

            $GLOBALS['__sims_backup_copy_false'] = [$this->rootPath . '/restored.txt'];

            try {
                file_put_contents($this->rootPath . '/artifact.txt', 'restore');
                $restoreFileArtifact->invoke($service, [
                    'name' => 'restore',
                    'kind' => 'file',
                    'path' => $this->rootPath . '/artifact.txt',
                    'source' => $this->rootPath . '/restored.txt',
                    'present' => true,
                ]);
                self::fail('Expected file restore failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to restore file artifact', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_copy_false']);
            }

            $GLOBALS['__sims_backup_scandir_false'] = [$this->rootPath . '/storage/app/private/uploads'];

            try {
                $copyDirectory->invoke($service, $this->rootPath . '/storage/app/private/uploads', $this->rootPath . '/copy-target');
                self::fail('Expected directory list failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to list directory', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_scandir_false']);
            }

            $GLOBALS['__sims_backup_scandir_false'] = [$this->rootPath . '/missing-clear'];

            try {
                mkdir($this->rootPath . '/missing-clear', 0775, true);
                $clearDirectory->invoke($service, $this->rootPath . '/missing-clear');
                self::fail('Expected clearDirectory scan failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to list directory', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_scandir_false']);
            }

            $GLOBALS['__sims_backup_unlink_false'] = [$this->rootPath . '/cannot-delete.txt'];
            file_put_contents($this->rootPath . '/cannot-delete.txt', 'x');

            try {
                $clearDirectory->invoke($service, $this->rootPath);
                self::fail('Expected clearDirectory unlink failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to remove file', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_unlink_false']);
            }

            $removeRoot = $this->rootPath . '/remove-tree';
            mkdir($removeRoot . '/nested', 0775, true);
            file_put_contents($removeRoot . '/nested/file.txt', 'x');
            $GLOBALS['__sims_backup_rmdir_false'] = [$removeRoot . '/nested'];

            try {
                $removeDirectory->invoke($service, $removeRoot);
                self::fail('Expected removeDirectory rmdir failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to remove directory', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_rmdir_false']);
                $this->removeDirectory($removeRoot);
            }

            $GLOBALS['__sims_backup_mkdir_false'] = [$this->rootPath . '/nope'];

            try {
                $ensureDirectory->invoke($service, $this->rootPath . '/nope');
                self::fail('Expected ensureDirectory failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to create directory', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_mkdir_false']);
            }

            self::assertSame([], $splitDumpStatements->invoke($service, ''));
            self::assertSame(['SELECT 1', 'SELECT 2'], $splitDumpStatements->invoke(
                $service,
                'SELECT 1' . "\n-- __SIMS_BACKUP_STATEMENT__\n" . 'SELECT 2'
            ));

            $pdo = new PDO('sqlite::memory:');
            self::assertSame('NULL', $sqlValue->invoke($service, $pdo, null));
            self::assertSame('1', $sqlValue->invoke($service, $pdo, true));
            self::assertSame('2', $sqlValue->invoke($service, $pdo, 2));
            self::assertSame('2.5', $sqlValue->invoke($service, $pdo, 2.5));
            self::assertSame("'quoted'", $sqlValue->invoke($service, $pdo, 'quoted'));
            $insertSql = $insertStatement->invoke(
                $service,
                $pdo,
                'sqlite',
                'users',
                ['id' => 1, 'email' => 'user@example.test']
            );
            self::assertIsString($insertSql);
            self::assertStringContainsString('INSERT INTO "users"', $insertSql);
            self::assertSame('`users`', $quoteIdentifier->invoke($service, 'mysql', 'users'));
            self::assertSame('"users"', $quoteIdentifier->invoke($service, 'sqlite', 'users'));

            $sqlite = new PDO('sqlite::memory:');
            $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            DatabaseBuilder::migrate($sqlite, 'sqlite');
            $sqliteTables = $tables->invoke($service, $sqlite, 'sqlite');
            self::assertIsArray($sqliteTables);
            self::assertContains('users', $sqliteTables);
            self::assertSame('sqlite', $driverName->invoke($service, $sqlite));
            $sqliteCreateStatement = $createTableStatement->invoke($service, $sqlite, 'sqlite', 'users');
            self::assertIsString($sqliteCreateStatement);
            self::assertStringContainsString('CREATE TABLE users', $sqliteCreateStatement);
            self::assertSame('', $dumpDatabase->invoke($service, new PDO('sqlite::memory:')));

            try {
                $createTableStatement->invoke($service, new PDO('sqlite::memory:'), 'sqlite', 'missing');
                self::fail('Expected missing create statement failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to resolve create statement', $exception->getMessage());
            }

            $mysqlLike = new class ('sqlite::memory:') extends PDO {
                private PDO $helper;

                public function __construct(string $dsn)
                {
                    parent::__construct($dsn);
                    $this->helper = new PDO('sqlite::memory:');
                }

                public function getAttribute(int $attribute): mixed
                {
                    if ($attribute === PDO::ATTR_DRIVER_NAME) {
                        return 'mysql';
                    }

                    return parent::getAttribute($attribute);
                }

                public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
                {
                    return match ($query) {
                        'SHOW TABLES' => $this->helper->query("SELECT 'users' AS table_name"),
                        'SHOW CREATE TABLE `users`' => $this->helper->query(
                            "SELECT 'users' AS table_name, 'CREATE TABLE users (id INTEGER)' AS definition"
                        ),
                        default => parent::query($query, $fetchMode, ...$fetchModeArgs),
                    };
                }
            };

            self::assertSame(['users'], $tables->invoke($service, $mysqlLike, 'mysql'));
            self::assertSame('mysql', $driverName->invoke($service, $mysqlLike));
            self::assertSame('CREATE TABLE users (id INTEGER)', $createTableStatement->invoke($service, $mysqlLike, 'mysql', 'users'));

            $manifest = $service->create();
            file_put_contents($manifest['database']['dump_path'], 'INVALID SQL');

            try {
                $restoreDatabase->invoke($service, $sqlite, $manifest);
                self::fail('Expected restoreDatabase failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to restore database backup.', $exception->getMessage());
            }

            $resolvedBackupPath = $backupPath->invoke($service, 'example-id');
            self::assertIsString($resolvedBackupPath);
            self::assertStringContainsString('/storage/backups/example-id', $resolvedBackupPath);
            self::assertSame('test-build', $appVersion->invoke($service));
        }

        public function testBackupServiceCoversRemainingCreateReadMysqlAndFilesystemBranches(): void
        {
            ['service' => $service] = $this->createEnvironment();

            $readManifest = new ReflectionMethod(BackupService::class, 'readManifest');
            $readManifest->setAccessible(true);
            $tables = new ReflectionMethod(BackupService::class, 'tables');
            $tables->setAccessible(true);
            $createTableStatement = new ReflectionMethod(BackupService::class, 'createTableStatement');
            $createTableStatement->setAccessible(true);
            $tableRows = new ReflectionMethod(BackupService::class, 'tableRows');
            $tableRows->setAccessible(true);
            $sqlValue = new ReflectionMethod(BackupService::class, 'sqlValue');
            $sqlValue->setAccessible(true);
            $restoreDatabase = new ReflectionMethod(BackupService::class, 'restoreDatabase');
            $restoreDatabase->setAccessible(true);
            $copyDirectory = new ReflectionMethod(BackupService::class, 'copyDirectory');
            $copyDirectory->setAccessible(true);
            $clearDirectory = new ReflectionMethod(BackupService::class, 'clearDirectory');
            $clearDirectory->setAccessible(true);
            $removeDirectory = new ReflectionMethod(BackupService::class, 'removeDirectory');
            $removeDirectory->setAccessible(true);

            $GLOBALS['__sims_backup_file_put_contents_false_suffixes'] = ['/database.sql'];

            try {
                $service->create();
                self::fail('Expected backup dump write failure.');
            } catch (RuntimeException $exception) {
                self::assertSame('Unable to write database backup dump.', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_file_put_contents_false_suffixes']);
            }

            $backupRoot = $service->backupsPath();
            if (!is_dir($backupRoot)) {
                mkdir($backupRoot, 0775, true);
            }
            mkdir($backupRoot . '/orphan', 0775, true);
            $GLOBALS['__sims_backup_scandir_false'] = [$backupRoot];
            self::assertSame([], $service->list());
            unset($GLOBALS['__sims_backup_scandir_false']);
            self::assertSame([], $service->list());

            mkdir($backupRoot . '/invalid', 0775, true);
            file_put_contents($backupRoot . '/invalid/manifest.json', '{invalid-json');

            try {
                $readManifest->invoke($service, 'invalid');
                self::fail('Expected invalid manifest failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Backup manifest for [invalid] is invalid.', $exception->getMessage());
            }

            mkdir($backupRoot . '/unreadable', 0775, true);
            file_put_contents($backupRoot . '/unreadable/manifest.json', '{}');
            $GLOBALS['__sims_backup_file_get_contents_false'] = [$backupRoot . '/unreadable/manifest.json'];

            try {
                $readManifest->invoke($service, 'unreadable');
                self::fail('Expected unreadable manifest failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to read manifest for backup [unreadable].', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_file_get_contents_false']);
            }

            mkdir($backupRoot . '/artifact-skip', 0775, true);
            file_put_contents($backupRoot . '/artifact-skip/manifest.json', json_encode([
                'id' => 'artifact-skip',
                'created_at' => '2026-04-01T00:00:00+00:00',
                'version' => 'test-build',
                'path' => $backupRoot . '/artifact-skip',
                'database' => [
                    'driver' => 'sqlite',
                    'target' => 'app.sqlite',
                    'dump_path' => $backupRoot . '/artifact-skip/database.sql',
                    'tables' => [],
                ],
                'artifacts' => [
                    'skip-me',
                    [
                        'name' => 'only',
                        'kind' => 'file',
                        'path' => '/tmp/only',
                        'source' => '/tmp/only-source',
                        'present' => true,
                        'size_bytes' => 4,
                        'files' => [
                            'skip-file',
                            [
                                'relative_path' => 'only',
                                'path' => '/tmp/only',
                                'checksum' => 'hash',
                                'size_bytes' => 4,
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR));

            /** @var array{artifacts: list<array{name: string}>} $artifactManifest */
            $artifactManifest = $readManifest->invoke($service, 'artifact-skip');
            self::assertCount(1, $artifactManifest['artifacts']);
            self::assertSame('only', $artifactManifest['artifacts'][0]['name']);

            $sqliteQueryFalse = new class ('sqlite::memory:') extends PDO {
                public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
                {
                    if (str_contains($query, 'sqlite_master')) {
                        return false;
                    }

                    return parent::query($query, $fetchMode, ...$fetchModeArgs);
                }
            };

            try {
                $tables->invoke($service, $sqliteQueryFalse, 'sqlite');
                self::fail('Expected sqlite table listing failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to list SQLite tables.', $exception->getMessage());
            }

            $mysqlShowTablesFalse = new class ('sqlite::memory:') extends PDO {
                public function getAttribute(int $attribute): mixed
                {
                    return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : parent::getAttribute($attribute);
                }

                public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
                {
                    return $query === 'SHOW TABLES' ? false : parent::query($query, $fetchMode, ...$fetchModeArgs);
                }
            };

            try {
                $tables->invoke($service, $mysqlShowTablesFalse, 'mysql');
                self::fail('Expected mysql table listing failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to list MySQL tables.', $exception->getMessage());
            }

            $mysqlCreateFalse = new class ('sqlite::memory:') extends PDO {
                public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
                {
                    return str_starts_with($query, 'SHOW CREATE TABLE') ? false : parent::query($query, $fetchMode, ...$fetchModeArgs);
                }
            };

            try {
                $createTableStatement->invoke($service, $mysqlCreateFalse, 'mysql', 'users');
                self::fail('Expected mysql create statement query failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to resolve create statement for table [users].', $exception->getMessage());
            }

            $mysqlCreateKey = new class ('sqlite::memory:') extends PDO {
                private PDO $helper;

                public function __construct(string $dsn)
                {
                    parent::__construct($dsn);
                    $this->helper = new PDO('sqlite::memory:');
                }

                public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
                {
                    return str_starts_with($query, 'SHOW CREATE TABLE')
                        ? $this->helper->query("SELECT 'users' AS `Table`, 'CREATE TABLE users (id INTEGER)' AS `Create Table`")
                        : parent::query($query, $fetchMode, ...$fetchModeArgs);
                }
            };
            self::assertSame(
                'CREATE TABLE users (id INTEGER)',
                $createTableStatement->invoke($service, $mysqlCreateKey, 'mysql', 'users')
            );

            $mysqlCreateNoRow = new class ('sqlite::memory:') extends PDO {
                private PDO $helper;

                public function __construct(string $dsn)
                {
                    parent::__construct($dsn);
                    $this->helper = new PDO('sqlite::memory:');
                }

                public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
                {
                    return str_starts_with($query, 'SHOW CREATE TABLE')
                        ? $this->helper->query('SELECT 1 WHERE 0')
                        : parent::query($query, $fetchMode, ...$fetchModeArgs);
                }
            };

            try {
                $createTableStatement->invoke($service, $mysqlCreateNoRow, 'mysql', 'users');
                self::fail('Expected mysql create statement fetch failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to resolve create statement for table [users].', $exception->getMessage());
            }

            $mysqlCreateEmptyFallback = new class ('sqlite::memory:') extends PDO {
                private PDO $helper;

                public function __construct(string $dsn)
                {
                    parent::__construct($dsn);
                    $this->helper = new PDO('sqlite::memory:');
                }

                public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
                {
                    return str_starts_with($query, 'SHOW CREATE TABLE')
                        ? $this->helper->query("SELECT 'users' AS table_name")
                        : parent::query($query, $fetchMode, ...$fetchModeArgs);
                }
            };

            try {
                $createTableStatement->invoke($service, $mysqlCreateEmptyFallback, 'mysql', 'users');
                self::fail('Expected mysql create statement fallback failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to resolve create statement for table [users].', $exception->getMessage());
            }

            $tableRowsFalse = new class ('sqlite::memory:') extends PDO {
                public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
                {
                    return str_starts_with($query, 'SELECT *') ? false : parent::query($query, $fetchMode, ...$fetchModeArgs);
                }
            };

            try {
                $tableRows->invoke($service, $tableRowsFalse, 'sqlite', 'users');
                self::fail('Expected table row read failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to read rows for table [users].', $exception->getMessage());
            }

            $quoteFalse = new class ('sqlite::memory:') extends PDO {
                public function quote(string $string, int $type = PDO::PARAM_STR): string|false
                {
                    return false;
                }
            };

            try {
                $sqlValue->invoke($service, $quoteFalse, 'value');
                self::fail('Expected quote failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to quote database backup value.', $exception->getMessage());
            }

            $manifest = $service->create();
            $GLOBALS['__sims_backup_file_get_contents_false'] = [$manifest['database']['dump_path']];

            try {
                $restoreDatabase->invoke($service, new PDO('sqlite::memory:'), $manifest);
                self::fail('Expected dump read failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to read database dump for backup', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_file_get_contents_false']);
            }

            $mysqlRestore = new class ('sqlite::memory:') extends PDO {
                private PDO $helper;

                /** @var list<string> */
                public array $execStatements = [];

                public function __construct(string $dsn)
                {
                    parent::__construct($dsn);
                    $this->helper = new PDO('sqlite::memory:');
                }

                public function getAttribute(int $attribute): mixed
                {
                    return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : parent::getAttribute($attribute);
                }

                public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
                {
                    return $query === 'SHOW TABLES'
                        ? $this->helper->query("SELECT 'users' AS table_name")
                        : parent::query($query, $fetchMode, ...$fetchModeArgs);
                }

                public function beginTransaction(): bool
                {
                    return true;
                }

                public function commit(): bool
                {
                    return true;
                }

                public function inTransaction(): bool
                {
                    return false;
                }

                public function exec(string $statement): int
                {
                    $this->execStatements[] = $statement;

                    return 0;
                }
            };

            $mysqlManifest = $manifest;
            file_put_contents($mysqlManifest['database']['dump_path'], 'CREATE TABLE users (id INTEGER)');
            $restoreDatabase->invoke($service, $mysqlRestore, $mysqlManifest);
            self::assertContains('SET FOREIGN_KEY_CHECKS=0', $mysqlRestore->execStatements);
            self::assertContains('SET FOREIGN_KEY_CHECKS=1', $mysqlRestore->execStatements);

            file_put_contents($this->rootPath . '/storage/app/private/uploads/fail-copy.txt', 'copy-me');
            $GLOBALS['__sims_backup_copy_false'] = [$this->rootPath . '/copy-target/fail-copy.txt'];

            try {
                $copyDirectory->invoke(
                    $service,
                    $this->rootPath . '/storage/app/private/uploads',
                    $this->rootPath . '/copy-target'
                );
                self::fail('Expected recursive copy failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to copy artifact', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_copy_false']);
            }

            $missingDirectory = $this->rootPath . '/brand-new-directory';
            $clearDirectory->invoke($service, $missingDirectory);
            self::assertDirectoryExists($missingDirectory);

            $removeNoScan = $this->rootPath . '/remove-no-scan';
            mkdir($removeNoScan, 0775, true);
            $GLOBALS['__sims_backup_scandir_false'] = [$removeNoScan];

            try {
                $removeDirectory->invoke($service, $removeNoScan);
                self::fail('Expected removeDirectory scandir failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to list directory', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_scandir_false']);
            }

            $removeSuccess = $this->rootPath . '/remove-success';
            mkdir($removeSuccess . '/nested', 0775, true);
            file_put_contents($removeSuccess . '/nested/file.txt', 'nested');
            $removeDirectory->invoke($service, $removeSuccess);
            self::assertDirectoryDoesNotExist($removeSuccess);

            $removeUnlinkFailure = $this->rootPath . '/remove-unlink-failure';
            mkdir($removeUnlinkFailure, 0775, true);
            file_put_contents($removeUnlinkFailure . '/file.txt', 'x');
            $GLOBALS['__sims_backup_unlink_false'] = [$removeUnlinkFailure . '/file.txt'];

            try {
                $removeDirectory->invoke($service, $removeUnlinkFailure);
                self::fail('Expected removeDirectory unlink failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to remove file', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_unlink_false']);
            }
        }

        public function testIntegrityHelpersCoverRemainingVerificationBranches(): void
        {
            ['service' => $service] = $this->createEnvironment();

            $readManifest = new ReflectionMethod(BackupService::class, 'readManifest');
            $readManifest->setAccessible(true);
            $fileDetails = new ReflectionMethod(BackupService::class, 'fileDetails');
            $fileDetails->setAccessible(true);
            $directoryFiles = new ReflectionMethod(BackupService::class, 'directoryFiles');
            $directoryFiles->setAccessible(true);
            $snapshotDirectory = new ReflectionMethod(BackupService::class, 'snapshotDirectory');
            $snapshotDirectory->setAccessible(true);
            $assertManifestIntegrity = new ReflectionMethod(BackupService::class, 'assertManifestIntegrity');
            $assertManifestIntegrity->setAccessible(true);
            $assertDatabaseDumpIntegrity = new ReflectionMethod(BackupService::class, 'assertDatabaseDumpIntegrity');
            $assertDatabaseDumpIntegrity->setAccessible(true);
            $assertArtifactIntegrity = new ReflectionMethod(BackupService::class, 'assertArtifactIntegrity');
            $assertArtifactIntegrity->setAccessible(true);
            $assertArtifactFileIntegrity = new ReflectionMethod(BackupService::class, 'assertArtifactFileIntegrity');
            $assertArtifactFileIntegrity->setAccessible(true);
            $restoreFileArtifact = new ReflectionMethod(BackupService::class, 'restoreFileArtifact');
            $restoreFileArtifact->setAccessible(true);
            $restoreDirectoryArtifact = new ReflectionMethod(BackupService::class, 'restoreDirectoryArtifact');
            $restoreDirectoryArtifact->setAccessible(true);
            $restoreDatabase = new ReflectionMethod(BackupService::class, 'restoreDatabase');
            $restoreDatabase->setAccessible(true);

            $manifest = $service->create();
            /** @var array{
             *     id: string,
             *     artifact_count: int,
             *     total_bytes: int,
             *     table_count: int,
             *     database: array{dump_path: string, checksum: string, size_bytes: int, tables: list<string>},
             *     artifacts: list<array{name: string, kind: string, path: string, source: string, present: bool, size_bytes: int, files: list<array{relative_path: string, path: string, checksum: string, size_bytes: int}>}>
             * } $typedManifest
             */
            $typedManifest = $readManifest->invoke($service, $manifest['id']);

            $GLOBALS['__sims_backup_file_get_contents_false'] = [$typedManifest['database']['dump_path']];

            try {
                $fileDetails->invoke($service, $typedManifest['database']['dump_path'], 'database.sql');
                self::fail('Expected fileDetails read failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to read backup artifact', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_file_get_contents_false']);
            }

            $GLOBALS['__sims_backup_scandir_false'] = [$manifest['path'] . '/files/private-uploads'];

            try {
                $directoryFiles->invoke($service, $manifest['path'] . '/files/private-uploads');
                self::fail('Expected directoryFiles scandir failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to list directory', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_scandir_false']);
            }

            /** @var array{present: bool, files: list<array<string, mixed>>, size_bytes: int} $missingDirectorySnapshot */
            $missingDirectorySnapshot = $snapshotDirectory->invoke(
                $service,
                'missing_dir',
                $this->rootPath . '/does-not-exist',
                $this->rootPath . '/snapshot-missing-dir'
            );
            self::assertFalse($missingDirectorySnapshot['present']);
            self::assertSame([], $missingDirectorySnapshot['files']);
            self::assertSame(0, $missingDirectorySnapshot['size_bytes']);

            $countMismatch = $typedManifest;
            $countMismatch['artifact_count']++;

            try {
                $assertManifestIntegrity->invoke($service, $countMismatch);
                self::fail('Expected artifact count mismatch failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('artifact count does not match', $exception->getMessage());
            }

            $totalBytesMismatch = $typedManifest;
            $totalBytesMismatch['total_bytes']++;

            try {
                $assertManifestIntegrity->invoke($service, $totalBytesMismatch);
                self::fail('Expected total bytes mismatch failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('total bytes do not match', $exception->getMessage());
            }

            $tableCountMismatch = $typedManifest;
            $tableCountMismatch['table_count']++;

            try {
                $assertManifestIntegrity->invoke($service, $tableCountMismatch);
                self::fail('Expected table count mismatch failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('table count does not match', $exception->getMessage());
            }

            $missingChecksum = $typedManifest;
            $missingChecksum['database']['checksum'] = '';

            try {
                $assertDatabaseDumpIntegrity->invoke($service, $missingChecksum);
                self::fail('Expected missing checksum failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Database dump checksum is missing', $exception->getMessage());
            }

            $sizeMismatch = $typedManifest;
            $sizeMismatch['database']['size_bytes']++;

            try {
                $assertDatabaseDumpIntegrity->invoke($service, $sizeMismatch);
                self::fail('Expected dump size mismatch failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Database dump size mismatch', $exception->getMessage());
            }

            try {
                $assertArtifactIntegrity->invoke($service, $typedManifest['id'], [
                    'name' => 'invalid',
                    'kind' => 'weird',
                    'path' => '',
                    'source' => '',
                    'present' => true,
                    'size_bytes' => 0,
                    'files' => [],
                ]);
                self::fail('Expected invalid artifact kind failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('invalid artifact type', $exception->getMessage());
            }

            try {
                $assertArtifactIntegrity->invoke($service, $typedManifest['id'], [
                    'name' => 'missing-file',
                    'kind' => 'file',
                    'path' => $this->rootPath . '/missing-file.txt',
                    'source' => $this->rootPath . '/missing-file.txt',
                    'present' => false,
                    'size_bytes' => 1,
                    'files' => [[
                        'relative_path' => 'missing-file.txt',
                        'path' => $this->rootPath . '/missing-file.txt',
                        'checksum' => 'hash',
                        'size_bytes' => 1,
                    ]],
                ]);
                self::fail('Expected invalid missing-file metadata failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('invalid metadata for missing file artifact', $exception->getMessage());
            }

            $assertArtifactIntegrity->invoke($service, $typedManifest['id'], [
                'name' => 'missing-file-valid',
                'kind' => 'file',
                'path' => $this->rootPath . '/missing-file-valid.txt',
                'source' => $this->rootPath . '/missing-file-valid.txt',
                'present' => false,
                'size_bytes' => 0,
                'files' => [],
            ]);

            $validArtifactFilePath = $this->rootPath . '/valid-artifact.txt';
            file_put_contents($validArtifactFilePath, 'artifact');
            $validArtifactFile = [
                'relative_path' => 'valid-artifact.txt',
                'path' => $validArtifactFilePath,
                'checksum' => hash('sha256', 'artifact'),
                'size_bytes' => strlen('artifact'),
            ];

            try {
                $assertArtifactIntegrity->invoke($service, $typedManifest['id'], [
                    'name' => 'single-file',
                    'kind' => 'file',
                    'path' => $validArtifactFilePath,
                    'source' => $validArtifactFilePath,
                    'present' => true,
                    'size_bytes' => $validArtifactFile['size_bytes'] * 2,
                    'files' => [$validArtifactFile, $validArtifactFile],
                ]);
                self::fail('Expected single-file tracking failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('must track exactly one file', $exception->getMessage());
            }

            try {
                $assertArtifactIntegrity->invoke($service, $typedManifest['id'], [
                    'name' => 'missing-dir',
                    'kind' => 'directory',
                    'path' => $this->rootPath . '/missing-directory',
                    'source' => $this->rootPath . '/missing-directory',
                    'present' => true,
                    'size_bytes' => 0,
                    'files' => [],
                ]);
                self::fail('Expected missing directory artifact failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Backup directory artifact [missing-dir] is missing.', $exception->getMessage());
            }

            try {
                $assertArtifactIntegrity->invoke($service, $typedManifest['id'], [
                    'name' => 'size-mismatch',
                    'kind' => 'directory',
                    'path' => dirname($validArtifactFilePath),
                    'source' => dirname($validArtifactFilePath),
                    'present' => true,
                    'size_bytes' => $validArtifactFile['size_bytes'] + 1,
                    'files' => [$validArtifactFile],
                ]);
                self::fail('Expected artifact size mismatch failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('size mismatch for artifact [size-mismatch]', $exception->getMessage());
            }

            try {
                $assertArtifactFileIntegrity->invoke($service, $typedManifest['id'], 'checksum-missing', [
                    'relative_path' => 'valid-artifact.txt',
                    'path' => $validArtifactFilePath,
                    'checksum' => '',
                    'size_bytes' => $validArtifactFile['size_bytes'],
                ]);
                self::fail('Expected missing artifact checksum failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('checksum is missing for artifact [checksum-missing]', $exception->getMessage());
            }

            try {
                $assertArtifactFileIntegrity->invoke($service, $typedManifest['id'], 'missing-artifact-file', [
                    'relative_path' => 'missing.txt',
                    'path' => $this->rootPath . '/missing-artifact-file.txt',
                    'checksum' => 'hash',
                    'size_bytes' => 1,
                ]);
                self::fail('Expected missing artifact file failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Backup file artifact [missing-artifact-file] is missing.', $exception->getMessage());
            }

            try {
                $assertArtifactFileIntegrity->invoke($service, $typedManifest['id'], 'checksum-mismatch', [
                    'relative_path' => 'valid-artifact.txt',
                    'path' => $validArtifactFilePath,
                    'checksum' => 'different',
                    'size_bytes' => $validArtifactFile['size_bytes'],
                ]);
                self::fail('Expected artifact checksum mismatch failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Backup checksum mismatch for artifact [checksum-mismatch]', $exception->getMessage());
            }

            try {
                $assertArtifactFileIntegrity->invoke($service, $typedManifest['id'], 'size-mismatch', [
                    'relative_path' => 'valid-artifact.txt',
                    'path' => $validArtifactFilePath,
                    'checksum' => $validArtifactFile['checksum'],
                    'size_bytes' => $validArtifactFile['size_bytes'] + 1,
                ]);
                self::fail('Expected artifact file size mismatch failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Backup size mismatch for artifact [size-mismatch]', $exception->getMessage());
            }

            try {
                $restoreFileArtifact->invoke($service, [
                    'name' => 'restore-missing',
                    'kind' => 'file',
                    'path' => $this->rootPath . '/restore-missing.txt',
                    'source' => $this->rootPath . '/restore-missing-target.txt',
                    'present' => true,
                    'size_bytes' => 0,
                    'files' => [],
                ]);
                self::fail('Expected missing restore file artifact failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Backup file artifact [restore-missing] is missing.', $exception->getMessage());
            }

            try {
                $restoreDirectoryArtifact->invoke($service, [
                    'name' => 'restore-missing-dir',
                    'kind' => 'directory',
                    'path' => $this->rootPath . '/restore-missing-dir',
                    'source' => $this->rootPath . '/restore-missing-dir-target',
                    'present' => true,
                    'size_bytes' => 0,
                    'files' => [],
                ]);
                self::fail('Expected missing restore directory artifact failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Backup directory artifact [restore-missing-dir] is missing.', $exception->getMessage());
            }

            @unlink($typedManifest['database']['dump_path']);

            try {
                $restoreDatabase->invoke($service, new PDO('sqlite::memory:'), $typedManifest);
                self::fail('Expected missing dump restoreDatabase failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Database dump for backup', $exception->getMessage());
            }
        }

        public function testExportAndArchiveHelpersCoverRemainingFailureBranches(): void
        {
            ['service' => $service] = $this->createEnvironment();
            $manifest = $service->create();

            $encryptArchive = new ReflectionMethod(BackupService::class, 'encryptArchive');
            $encryptArchive->setAccessible(true);
            $readExportEnvelope = new ReflectionMethod(BackupService::class, 'readExportEnvelope');
            $readExportEnvelope->setAccessible(true);
            $decryptArchive = new ReflectionMethod(BackupService::class, 'decryptArchive');
            $decryptArchive->setAccessible(true);
            $deriveExportKey = new ReflectionMethod(BackupService::class, 'deriveExportKey');
            $deriveExportKey->setAccessible(true);
            $createArchive = new ReflectionMethod(BackupService::class, 'createArchive');
            $createArchive->setAccessible(true);
            $extractArchive = new ReflectionMethod(BackupService::class, 'extractArchive');
            $extractArchive->setAccessible(true);

            $GLOBALS['__sims_backup_openssl_encrypt_false'] = true;

            try {
                $encryptArchive->invoke($service, $manifest['id'], 'backup.tar.gz', 'payload', 'test-export-key');
                self::fail('Expected encryptArchive failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to encrypt archive for backup', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_openssl_encrypt_false']);
            }

            $unreadableExport = $this->rootPath . '/unreadable-export.enc';
            file_put_contents($unreadableExport, '{}');
            $GLOBALS['__sims_backup_file_get_contents_false'] = [$unreadableExport];

            try {
                $readExportEnvelope->invoke($service, $unreadableExport);
                self::fail('Expected readExportEnvelope read failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to read backup export', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_file_get_contents_false']);
            }

            try {
                $decryptArchive->invoke($service, [
                    'version' => 0,
                    'backup_id' => '',
                    'created_at' => '',
                    'cipher' => '',
                    'kdf' => '',
                    'iterations' => 1,
                    'archive_name' => '',
                    'archive_checksum' => '',
                    'salt' => '',
                    'iv' => '',
                    'tag' => '',
                    'ciphertext' => '',
                ], 'test-export-key');
                self::fail('Expected invalid decrypt metadata failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Backup export metadata is invalid.', $exception->getMessage());
            }

            try {
                $decryptArchive->invoke($service, [
                    'version' => 1,
                    'backup_id' => 'backup-id',
                    'created_at' => date('c'),
                    'cipher' => 'aes-256-gcm',
                    'kdf' => 'pbkdf2-sha256',
                    'iterations' => 1,
                    'archive_name' => 'backup.tar.gz',
                    'archive_checksum' => hash('sha256', 'payload'),
                    'salt' => '***',
                    'iv' => base64_encode(random_bytes(12)),
                    'tag' => base64_encode(random_bytes(16)),
                    'ciphertext' => base64_encode('payload'),
                ], 'test-export-key');
                self::fail('Expected malformed decrypt metadata failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('is malformed', $exception->getMessage());
            }

            /** @var array<string, mixed> $validEnvelope */
            $validEnvelope = $encryptArchive->invoke(
                $service,
                $manifest['id'],
                'backup.tar.gz',
                'payload',
                'test-export-key'
            );
            $validEnvelope['archive_checksum'] = hash('sha256', 'different-payload');

            try {
                $decryptArchive->invoke($service, $validEnvelope, 'test-export-key');
                self::fail('Expected decrypt checksum mismatch failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Backup export checksum mismatch', $exception->getMessage());
            }

            try {
                $deriveExportKey->invoke($service, 'test-export-key', 'salt', 0);
                self::fail('Expected invalid iteration failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('iterations must be greater than zero', $exception->getMessage());
            }

            $workingPath = $this->rootPath . '/archive-working';
            mkdir($workingPath, 0775, true);
            $tarPath = $workingPath . '/' . basename($manifest['path']) . '.tar';
            $GLOBALS['__sims_backup_unlink_false'] = [$tarPath];

            try {
                $createArchive->invoke($service, $manifest['path'], $workingPath);
                self::fail('Expected archive finalize failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to finalize archive for backup', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_unlink_false']);
            }

            $archiveReadWorkingPath = $this->rootPath . '/archive-working-read';
            mkdir($archiveReadWorkingPath, 0775, true);
            $archivePath = $createArchive->invoke($service, $manifest['path'], $archiveReadWorkingPath);
            self::assertIsString($archivePath);

            $missingCompressedWorkingPath = $this->rootPath . '/archive-working-missing-compressed';
            mkdir($missingCompressedWorkingPath, 0775, true);
            $missingCompressedArchivePath = $missingCompressedWorkingPath . '/' . basename($manifest['path']) . '.tar.gz';
            $GLOBALS['__sims_backup_is_file_false'] = [$missingCompressedArchivePath];

            try {
                $createArchive->invoke($service, $manifest['path'], $missingCompressedWorkingPath);
                self::fail('Expected compressed archive missing failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Compressed archive for backup', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_is_file_false']);
            }

            $decompressArchivePath = $this->rootPath . '/archive-copy-decompress.tar.gz';
            copy($archivePath, $decompressArchivePath);
            $extractTarget = $this->rootPath . '/extract-target';
            mkdir($extractTarget, 0775, true);
            $tarFromArchive = substr($decompressArchivePath, 0, -3);
            $GLOBALS['__sims_backup_is_file_false'] = [$tarFromArchive];

            try {
                $extractArchive->invoke($service, $decompressArchivePath, $extractTarget);
                self::fail('Expected archive decompress failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('could not be decompressed', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_is_file_false']);
            }

            $inspectArchivePath = $this->rootPath . '/archive-copy-inspect.tar.gz';
            copy($archivePath, $inspectArchivePath);
            $GLOBALS['__sims_backup_scandir_false'] = [$extractTarget];

            try {
                $extractArchive->invoke($service, $inspectArchivePath, $extractTarget);
                self::fail('Expected extracted archive inspection failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to inspect extracted backup archive', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_scandir_false']);
            }

            $multiRootTar = $this->rootPath . '/multi-root.tar';
            $multiRootArchive = new \PharData($multiRootTar);
            $multiRootArchive->addEmptyDir('one');
            $multiRootArchive->addFromString('one/file.txt', 'one');
            $multiRootArchive->addEmptyDir('two');
            $multiRootArchive->addFromString('two/file.txt', 'two');
            $multiRootArchive->compress(\Phar::GZ);
            unset($multiRootArchive);
            unlink($multiRootTar);
            $multiRootArchivePath = $this->rootPath . '/multi-root-copy.tar.gz';
            copy($multiRootTar . '.gz', $multiRootArchivePath);

            try {
                $extractArchive->invoke($service, $multiRootArchivePath, $this->rootPath . '/multi-root-extract');
                self::fail('Expected multiple backup directories failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('does not contain exactly one backup directory', $exception->getMessage());
            }
        }

        /**
         * @return array{service: BackupService, database: Database, log_file: string, database_file: string}
         */
        private function createEnvironment(): array
        {
            return $this->createEnvironmentAt($this->rootPath);
        }

        /**
         * @return array{service: BackupService, database: Database, log_file: string, database_file: string, root: string}
         */
        private function createEnvironmentAt(
            string $rootPath,
            string $exportKey = 'test-export-key',
            ?callable $remoteRequestHandler = null,
            bool $configureRemote = true
        ): array {
            if (!is_dir($rootPath . '/storage/app/private/uploads/portraits')) {
                mkdir($rootPath . '/storage/app/private/uploads/portraits', 0775, true);
            }

            if (!is_dir($rootPath . '/storage/app/public/id-cards')) {
                mkdir($rootPath . '/storage/app/public/id-cards', 0775, true);
            }

            if (!is_dir($rootPath . '/storage/logs')) {
                mkdir($rootPath . '/storage/logs', 0775, true);
            }

            if (!is_dir($rootPath . '/storage/framework/sessions')) {
                mkdir($rootPath . '/storage/framework/sessions', 0775, true);
            }

            if (!is_dir($rootPath . '/storage/database')) {
                mkdir($rootPath . '/storage/database', 0775, true);
            }

            if (!is_file($rootPath . '/.env')) {
                file_put_contents($rootPath . '/.env', "APP_ENV=production\nAPP_KEY=testing\n");
            }

            if (!is_file($rootPath . '/storage/app/private/uploads/portraits/demo.txt')) {
                file_put_contents($rootPath . '/storage/app/private/uploads/portraits/demo.txt', 'portrait');
            }

            if (!is_file($rootPath . '/storage/app/public/id-cards/card.txt')) {
                file_put_contents($rootPath . '/storage/app/public/id-cards/card.txt', 'card');
            }

            $databaseFile = $rootPath . '/storage/database/app.sqlite';
            touch($databaseFile);

            $config = new Config([
                'app' => [
                    'version' => 'test-build',
                ],
                'db' => [
                    'driver' => 'sqlite',
                    'database' => $databaseFile,
                ],
                'backup' => [
                    'export_key' => $exportKey,
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
                'session' => [
                    'path' => $rootPath . '/storage/framework/sessions',
                ],
            ]);
            $context = new RequestContext();
            $context->startConsole('backup-test-request');
            $logFile = $rootPath . '/storage/logs/app.log';
            $logger = new Logger($logFile, $context);
            $database = new Database($config, $logger);
            $remoteStore = new S3BackupRemoteStore(
                $config,
                $remoteRequestHandler ?? $this->unexpectedRemoteRequestHandler()
            );
            $pdo = $database->connection();
            DatabaseBuilder::migrate($pdo, 'sqlite');

            $pdo->exec(
                "INSERT INTO users (id, name, email, password_hash, role, department, created_at, updated_at)
             VALUES (1, 'Admin', 'admin@example.test', 'hash', 'admin', 'Registrar', '2026-04-01 00:00:00', '2026-04-01 00:00:00')"
            );
            $pdo->exec(
                "INSERT INTO students (
                id, student_number, first_name, middle_name, last_name, birthdate, program, year_level,
                email, phone, address, guardian_name, guardian_contact, department, enrollment_status, photo_path, created_at, updated_at
             ) VALUES (
                1, 'ST-2026-1001', 'Leah', '', 'Ramos', '2000-01-01', 'BSCS', '3',
                'student@example.test', '09123456789', 'Manila', 'Maria Ramos', '09112223333', 'Computing',
                'Active', 'portraits/demo.txt', '2026-04-01 00:00:00', '2026-04-01 00:00:00'
             )"
            );
            $pdo->exec(
                "INSERT INTO id_cards (student_id, file_path, qr_payload, barcode_payload, generated_by, generated_at)
             VALUES (1, 'card.txt', 'qr-data', 'barcode-data', 1, '2026-04-01 00:00:00')"
            );

            return [
                'service' => new BackupService($database, $config, $logger, $rootPath, $remoteStore),
                'database' => $database,
                'log_file' => $logFile,
                'database_file' => $databaseFile,
                'root' => $rootPath,
            ];
        }

        /**
         * @param array<string, array{body: string, headers: array<string, string>}> $remoteObjects
         * @return callable(string, string, array<string, string>, string): array{status: int, headers: array<string, string>, body: string}
         */
        private function remoteRequestHandler(array &$remoteObjects): callable
        {
            $handler = /**
             * @param array<string, string> $headers
             * @return array{status: int, headers: array<string, string>, body: string}
             */
            function (string $method, string $url, array $headers, string $body) use (&$remoteObjects): array {
                return $this->handleRemoteRequest($remoteObjects, $method, $url, $headers, $body);
            };

            return $handler;
        }

        /**
         * @param array<string, array{body: string, headers: array<string, string>}> $remoteObjects
         * @param array<mixed> $headers
         * @return array{status: int, headers: array<string, string>, body: string}
         */
        private function handleRemoteRequest(
            array &$remoteObjects,
            string $method,
            string $url,
            array $headers,
            string $body
        ): array {
            $parts = parse_url($url);
            self::assertIsArray($parts);
            $path = trim((string) ($parts['path'] ?? ''), '/');
            $segments = $path === '' ? [] : explode('/', $path);
            $objectKey = implode('/', array_slice($segments, 1));

            if ($method === 'PUT') {
                $remoteObjects[$objectKey] = [
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

                foreach (array_keys($remoteObjects) as $key) {
                    $items .= '<Contents><Key>' . e($key) . '</Key></Contents>';
                }

                return [
                    'status' => 200,
                    'headers' => [],
                    'body' => '<ListBucketResult>' . $items . '</ListBucketResult>',
                ];
            }

            $object = $remoteObjects[$objectKey] ?? null;

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
                unset($remoteObjects[$objectKey]);

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
        }

        /**
         * @return callable(string, string, array<string, string>, string): array{status: int, headers: array<string, string>, body: string}
         */
        private function unexpectedRemoteRequestHandler(): callable
        {
            return static function (string $method, string $url): array {
                throw new RuntimeException(sprintf('Unexpected remote request [%s %s].', $method, $url));
            };
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

        private function clearBackupGlobal(string $key): void
        {
            if (array_key_exists($key, $GLOBALS)) {
                unset($GLOBALS[$key]);
            }
        }

        private function removeDirectory(string $directory): void
        {
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

}
