<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use FilesystemIterator;
use PDO;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * @phpstan-type BackupArtifactFile array{
 *     relative_path: string,
 *     path: string,
 *     checksum: string,
 *     size_bytes: int
 * }
 * @phpstan-type BackupArtifact array{
 *     name: string,
 *     kind: string,
 *     path: string,
 *     source: string,
 *     present: bool,
 *     size_bytes: int,
 *     files: list<BackupArtifactFile>
 * }
 * @phpstan-type BackupManifest array{
 *     id: string,
 *     created_at: string,
 *     version: string|null,
 *     path: string,
 *     artifact_count: int,
 *     total_bytes: int,
 *     table_count: int,
 *     database: array{
 *         driver: string,
 *         target: string,
 *         dump_path: string,
 *         tables: list<string>,
 *         checksum: string,
 *         size_bytes: int
 *     },
 *     artifacts: list<BackupArtifact>
 * }
 * @phpstan-type BackupPruneResult array{
 *     deleted: list<string>,
 *     retained: int
 * }
 * @phpstan-type BackupExportResult array{
 *     backup_id: string,
 *     export_path: string,
 *     archive_name: string,
 *     archive_checksum: string,
 *     encrypted_checksum: string,
 *     created_at: string,
 *     size_bytes: int
 * }
 * @phpstan-type BackupImportResult array{
 *     backup_id: string,
 *     path: string,
 *     export_path: string
 * }
 * @phpstan-type BackupDrillResult array{
 *     backup_id: string,
 *     artifact_count: int,
 *     total_bytes: int,
 *     table_count: int
 * }
 * @phpstan-type BackupRemoteObject array{
 *     object_key: string,
 *     backup_id: string,
 *     archive_checksum: string,
 *     encrypted_checksum: string,
 *     created_at: string,
 *     manifest_version: string,
 *     size_bytes: int
 * }
 * @phpstan-type BackupRemotePushResult array{
 *     object_key: string,
 *     backup_id: string,
 *     export_path: string,
 *     archive_checksum: string,
 *     encrypted_checksum: string,
 *     created_at: string,
 *     manifest_version: string,
 *     size_bytes: int
 * }
 * @phpstan-type BackupRemotePullResult array{
 *     object_key: string,
 *     backup_id: string,
 *     export_path: string,
 *     archive_checksum: string,
 *     encrypted_checksum: string,
 *     created_at: string,
 *     manifest_version: string,
 *     size_bytes: int
 * }
 * @phpstan-type BackupRemotePruneResult array{
 *     deleted: list<string>,
 *     retained: int
 * }
 * @phpstan-type BackupExportEnvelope array{
 *     version: int,
 *     backup_id: string,
 *     created_at: string,
 *     cipher: string,
 *     kdf: string,
 *     iterations: int,
 *     archive_name: string,
 *     archive_checksum: string,
 *     salt: string,
 *     iv: string,
 *     tag: string,
 *     ciphertext: string
 * }
 */
final class BackupService
{
    private const STATEMENT_SEPARATOR = "\n-- __SIMS_BACKUP_STATEMENT__\n";
    private const EXPORT_CIPHER = 'aes-256-gcm';
    private const EXPORT_KDF = 'pbkdf2-sha256';
    private const EXPORT_KDF_ITERATIONS = 100000;

    public function __construct(
        private readonly Database $database,
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly string $rootPath,
        private readonly S3BackupRemoteStore $remoteStore
    ) {
    }

    /**
     * @return BackupManifest
     */
    public function create(): array
    {
        $backupId = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $backupPath = $this->backupPath($backupId);
        $filesPath = $backupPath . '/files';

        $this->ensureDirectory($backupPath);
        $this->ensureDirectory($filesPath);

        $database = $this->database->connection();
        $driver = $this->driverName($database);
        $dumpPath = $backupPath . '/database.sql';
        $tables = $this->tables($database, $driver);
        $dumpWritten = file_put_contents($dumpPath, $this->dumpDatabase($database));

        if ($dumpWritten === false) {
            throw new RuntimeException('Unable to write database backup dump.');
        }

        $databaseFile = $this->fileDetails($dumpPath, 'database.sql');

        $artifacts = [
            $this->snapshotFile('.env', $this->rootPath . '/.env', $filesPath . '/.env'),
            $this->snapshotDirectory(
                'private_uploads',
                $this->rootPath . '/storage/app/private/uploads',
                $filesPath . '/private-uploads'
            ),
            $this->snapshotDirectory(
                'public_id_cards',
                $this->rootPath . '/storage/app/public/id-cards',
                $filesPath . '/public-id-cards'
            ),
        ];
        $artifactCount = $this->artifactCount($artifacts);
        $artifactBytes = $this->artifactBytes($artifacts);

        $manifest = [
            'id' => $backupId,
            'created_at' => date('c'),
            'version' => $this->appVersion(),
            'path' => $backupPath,
            'artifact_count' => $artifactCount,
            'total_bytes' => $databaseFile['size_bytes'] + $artifactBytes,
            'table_count' => count($tables),
            'database' => [
                'driver' => $driver,
                'target' => string_value($this->config->get('db.database', '')),
                'dump_path' => $dumpPath,
                'tables' => $tables,
                'checksum' => $databaseFile['checksum'],
                'size_bytes' => $databaseFile['size_bytes'],
            ],
            'artifacts' => $artifacts,
        ];

        $this->writeManifest($backupPath, $manifest);
        $this->logger->info('Backup created.', [
            'event' => 'backup.create.completed',
            'backup_id' => $backupId,
            'driver' => $driver,
            'tables' => $manifest['database']['tables'],
            'artifacts' => array_column($artifacts, 'name'),
        ], 'operations');

        return $manifest;
    }

    /**
     * @return BackupManifest
     */
    public function verify(string $backupId): array
    {
        return $this->loadVerifiedManifest($backupId, 'Backup verification failed.', true);
    }

    /**
     * @return list<BackupManifest>
     */
    public function list(): array
    {
        if (!is_dir($this->backupsPath())) {
            return [];
        }

        $entries = scandir($this->backupsPath());
        if ($entries === false) {
            return [];
        }

        $backups = [];

        foreach ($entries as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }

            $manifestPath = $this->backupPath($entry) . '/manifest.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            try {
                $backups[] = $this->readManifest($entry);
            } catch (Throwable) {
                continue;
            }
        }

        usort($backups, static fn (array $left, array $right): int => strcmp(
            string_value($right['created_at']),
            string_value($left['created_at'])
        ));

        /** @var list<BackupManifest> $backups */
        return $backups;
    }

    /**
     * @return BackupManifest
     */
    public function restore(string $backupId): array
    {
        $manifest = $this->loadVerifiedManifest($backupId, 'Backup restore verification failed.');
        $this->logger->info('Backup restore started.', [
            'event' => 'backup.restore.started',
            'backup_id' => $backupId,
        ], 'operations');

        try {
            $database = $this->database->connection();
            $this->restoreDatabase($database, $manifest);
            $this->restoreArtifacts($manifest);
        } catch (Throwable $exception) {
            $this->logger->error('Backup restore failed.', [
                'event' => 'backup.restore.failed',
                'backup_id' => $backupId,
                'message' => $exception->getMessage(),
            ], 'operations');

            throw $exception;
        }

        $this->logger->info('Backup restored.', [
            'event' => 'backup.restore.completed',
            'backup_id' => $backupId,
            'driver' => $manifest['database']['driver'],
        ], 'operations');

        return $manifest;
    }

    /**
     * @return BackupPruneResult
     */
    public function prune(int $keep = 10): array
    {
        if ($keep < 0) {
            throw new RuntimeException('Backup retention keep count must be zero or greater.');
        }

        $backups = $this->list();
        /** @var list<string> $deleted */
        $deleted = [];

        foreach (array_slice($backups, $keep) as $backup) {
            $path = $this->backupPath($backup['id']);

            if (!is_dir($path)) {
                throw new RuntimeException(sprintf('Backup directory [%s] is missing.', $path));
            }

            $this->removeDirectory($path);
            $deleted[] = $backup['id'];
        }

        $retained = min(count($backups), $keep);
        $this->logger->info('Backups pruned.', [
            'event' => 'backup.prune.completed',
            'keep' => $keep,
            'retained' => $retained,
            'deleted' => $deleted,
        ], 'operations');

        return [
            'deleted' => $deleted,
            'retained' => $retained,
        ];
    }

    /**
     * @return BackupExportResult
     */
    public function export(string $backupId, ?string $passphrase = null): array
    {
        $manifest = $this->loadVerifiedManifest($backupId, 'Backup export verification failed.');
        $resolvedPassphrase = $this->resolveExportPassphrase($passphrase);
        $this->ensureDirectory($this->exportsPath());
        $this->logger->info('Backup export started.', [
            'event' => 'backup.export.started',
            'backup_id' => $backupId,
        ], 'operations');

        $workingPath = $this->temporaryPath('backup-export');

        try {
            $this->ensureDirectory($workingPath);
            $archivePath = $this->createArchive($manifest['path'], $workingPath);
            $archivePayload = file_get_contents($archivePath);

            if ($archivePayload === false) {
                throw new RuntimeException(sprintf('Unable to read archive for backup [%s].', $backupId));
            }

            $archiveName = basename($archivePath);
            $archiveChecksum = hash('sha256', $archivePayload);
            $exportPath = $this->exportPath($backupId);
            $envelope = $this->encryptArchive($backupId, $archiveName, $archivePayload, $resolvedPassphrase);
            $encodedEnvelope = json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $written = file_put_contents($exportPath, $encodedEnvelope . PHP_EOL);

            if ($written === false) {
                throw new RuntimeException(sprintf('Unable to write backup export for [%s].', $backupId));
            }

            $result = [
                'backup_id' => $backupId,
                'export_path' => $exportPath,
                'archive_name' => $archiveName,
                'archive_checksum' => $archiveChecksum,
                'encrypted_checksum' => hash('sha256', (string) file_get_contents($exportPath)),
                'created_at' => $this->exportCreatedAt($exportPath),
                'size_bytes' => filesize($exportPath) ?: 0,
            ];

            $this->logger->info('Backup export completed.', [
                ...$result,
                'event' => 'backup.export.completed',
            ], 'operations');

            return $result;
        } catch (Throwable $exception) {
            $this->logger->error('Backup export failed.', [
                'event' => 'backup.export.failed',
                'backup_id' => $backupId,
                'message' => $exception->getMessage(),
            ], 'operations');

            throw $exception;
        } finally {
            $this->cleanupTemporaryPath($workingPath);
        }
    }

    /**
     * @return BackupImportResult
     */
    public function import(string $archivePath, ?string $passphrase = null): array
    {
        if (!is_file($archivePath)) {
            throw new RuntimeException(sprintf('Backup export [%s] was not found.', $archivePath));
        }

        $resolvedPassphrase = $this->resolveExportPassphrase($passphrase);
        $this->logger->info('Backup import started.', [
            'event' => 'backup.import.started',
            'export_path' => $archivePath,
        ], 'operations');

        $workingPath = $this->temporaryPath('backup-import');
        $importedBackupPath = null;

        try {
            $this->ensureDirectory($workingPath);
            $envelope = $this->readExportEnvelope($archivePath);
            $archivePayload = $this->decryptArchive($envelope, $resolvedPassphrase);
            $archiveName = string_value($envelope['archive_name'] ?? 'backup.tar.gz', 'backup.tar.gz');
            $materializedArchivePath = $workingPath . '/' . $archiveName;
            $archiveWritten = file_put_contents($materializedArchivePath, $archivePayload);

            if ($archiveWritten === false) {
                throw new RuntimeException(sprintf('Unable to materialize archive for backup [%s].', $envelope['backup_id']));
            }

            $extractionRoot = $workingPath . '/extract';
            $this->ensureDirectory($extractionRoot);
            $extractedBackupPath = $this->extractArchive($materializedArchivePath, $extractionRoot);
            $extractedManifest = $this->normalizeManifestForCurrentRoot(
                $this->readManifestAtPath($extractedBackupPath),
                $extractedBackupPath
            );
            $backupId = $extractedManifest['id'];

            if ($backupId !== $envelope['backup_id']) {
                throw new RuntimeException(sprintf(
                    'Backup export [%s] does not match extracted backup [%s].',
                    $envelope['backup_id'],
                    $backupId
                ));
            }

            $importedBackupPath = $this->backupPath($backupId);

            if (is_dir($importedBackupPath)) {
                throw new RuntimeException(sprintf('Backup [%s] already exists in the local store.', $backupId));
            }

            $this->copyDirectory($extractedBackupPath, $importedBackupPath);
            $rebasedManifest = $this->normalizeManifestForCurrentRoot(
                $this->readManifestAtPath($importedBackupPath),
                $importedBackupPath
            );
            $this->writeManifest($importedBackupPath, $rebasedManifest);
            $this->verify($backupId);

            $result = [
                'backup_id' => $backupId,
                'path' => $importedBackupPath,
                'export_path' => $archivePath,
            ];
            $this->logger->info('Backup import completed.', [
                ...$result,
                'event' => 'backup.import.completed',
            ], 'operations');

            return $result;
        } catch (Throwable $exception) {
            if (is_string($importedBackupPath) && is_dir($importedBackupPath)) {
                $this->cleanupTemporaryPath($importedBackupPath);
            }

            $this->logger->error('Backup import failed.', [
                'event' => 'backup.import.failed',
                'export_path' => $archivePath,
                'message' => $exception->getMessage(),
            ], 'operations');

            throw $exception;
        } finally {
            $this->cleanupTemporaryPath($workingPath);
        }
    }

    /**
     * @return BackupDrillResult
     */
    public function drill(string $backupId): array
    {
        $this->logger->info('Backup drill started.', [
            'event' => 'backup.drill.started',
            'backup_id' => $backupId,
        ], 'operations');

        $workingPath = $this->temporaryPath('backup-drill');

        try {
            $manifest = $this->loadVerifiedManifest($backupId, 'Backup drill verification failed.');
            $this->ensureDirectory($workingPath);
            $workspacePath = $workingPath . '/' . $backupId;
            $this->copyDirectory($manifest['path'], $workspacePath);
            $drillManifest = $this->normalizeManifestForCurrentRoot(
                $this->readManifestAtPath($workspacePath),
                $workspacePath
            );
            $this->assertManifestIntegrity($drillManifest);

            $dumpPayload = file_get_contents($drillManifest['database']['dump_path']);
            if ($dumpPayload === false) {
                throw new RuntimeException(sprintf('Unable to read drill database dump for backup [%s].', $backupId));
            }

            $this->splitDumpStatements($dumpPayload);

            $result = [
                'backup_id' => $backupId,
                'artifact_count' => $drillManifest['artifact_count'],
                'total_bytes' => $drillManifest['total_bytes'],
                'table_count' => $drillManifest['table_count'],
            ];
            $this->logger->info('Backup drill completed.', [
                ...$result,
                'event' => 'backup.drill.completed',
            ], 'operations');

            return $result;
        } catch (Throwable $exception) {
            $this->logger->error('Backup drill failed.', [
                'event' => 'backup.drill.failed',
                'backup_id' => $backupId,
                'message' => $exception->getMessage(),
            ], 'operations');

            throw $exception;
        } finally {
            $this->cleanupTemporaryPath($workingPath);
        }
    }

    /**
     * @return BackupRemotePushResult
     */
    public function push(string $backupId): array
    {
        $manifest = $this->loadVerifiedManifest($backupId, 'Remote backup push verification failed.');
        $this->logger->info('Remote backup push started.', [
            'event' => 'backup.push.started',
            'backup_id' => $backupId,
        ], 'operations');

        try {
            $export = $this->latestExportResult($backupId) ?? $this->export($backupId);
            $remoteObject = $this->remoteStore->push($export['export_path'], [
                'backup_id' => $backupId,
                'archive_checksum' => $export['archive_checksum'],
                'encrypted_checksum' => $export['encrypted_checksum'],
                'created_at' => $this->exportCreatedAt($export['export_path']),
                'manifest_version' => $manifest['version'] ?? 'unknown',
            ]);

            $result = [
                'object_key' => $remoteObject['object_key'],
                'backup_id' => $backupId,
                'export_path' => $export['export_path'],
                'archive_checksum' => $remoteObject['archive_checksum'],
                'encrypted_checksum' => $remoteObject['encrypted_checksum'],
                'created_at' => $remoteObject['created_at'],
                'manifest_version' => $remoteObject['manifest_version'],
                'size_bytes' => $remoteObject['size_bytes'],
            ];
            $this->logger->info('Remote backup push completed.', [
                ...$result,
                'event' => 'backup.push.completed',
            ], 'operations');

            return $result;
        } catch (Throwable $exception) {
            $this->logger->error('Remote backup push failed.', [
                'event' => 'backup.push.failed',
                'backup_id' => $backupId,
                'message' => $exception->getMessage(),
            ], 'operations');

            throw $exception;
        }
    }

    /**
     * @return list<BackupRemoteObject>
     */
    public function remoteList(): array
    {
        return $this->remoteStore->list();
    }

    /**
     * @return BackupRemotePruneResult
     */
    public function remotePrune(int $keep = 10): array
    {
        if ($keep < 0) {
            throw new RuntimeException('Remote backup retention keep count must be zero or greater.');
        }

        $objects = $this->remoteList();
        /** @var list<string> $deleted */
        $deleted = [];

        foreach (array_slice($objects, $keep) as $object) {
            $this->remoteStore->delete($object['object_key']);
            $deleted[] = $object['object_key'];
        }

        $retained = min(count($objects), $keep);
        $this->logger->info('Remote backups pruned.', [
            'event' => 'backup.remote_prune.completed',
            'keep' => $keep,
            'retained' => $retained,
            'deleted' => $deleted,
            'stage' => 'prune_remote',
        ], 'operations');

        return [
            'deleted' => $deleted,
            'retained' => $retained,
        ];
    }

    /**
     * @return BackupRemotePullResult
     */
    public function pull(string $objectKey): array
    {
        if ($objectKey === '') {
            throw new RuntimeException('Remote backup object key is required.');
        }

        $this->ensureDirectory($this->exportsPath());
        $exportPath = $this->exportsPath() . '/' . basename($objectKey);

        if (is_file($exportPath)) {
            throw new RuntimeException(sprintf('Backup export [%s] already exists locally.', $exportPath));
        }

        $this->logger->info('Remote backup pull started.', [
            'event' => 'backup.pull.started',
            'object_key' => $objectKey,
        ], 'operations');

        try {
            $remoteObject = $this->remoteStore->pull($objectKey, $exportPath);
            $payload = file_get_contents($exportPath);

            if ($payload === false) {
                throw new RuntimeException(sprintf('Unable to read downloaded backup export [%s].', $exportPath));
            }

            $checksum = hash('sha256', $payload);

            if ($checksum !== $remoteObject['encrypted_checksum']) {
                @unlink($exportPath);

                throw new RuntimeException(sprintf(
                    'Downloaded backup export checksum mismatch for remote object [%s].',
                    $objectKey
                ));
            }

            $result = [
                'object_key' => $remoteObject['object_key'],
                'backup_id' => $remoteObject['backup_id'],
                'export_path' => $exportPath,
                'archive_checksum' => $remoteObject['archive_checksum'],
                'encrypted_checksum' => $remoteObject['encrypted_checksum'],
                'created_at' => $remoteObject['created_at'],
                'manifest_version' => $remoteObject['manifest_version'],
                'size_bytes' => $remoteObject['size_bytes'],
            ];
            $this->logger->info('Remote backup pull completed.', [
                ...$result,
                'event' => 'backup.pull.completed',
            ], 'operations');

            return $result;
        } catch (Throwable $exception) {
            @unlink($exportPath);

            $this->logger->error('Remote backup pull failed.', [
                'event' => 'backup.pull.failed',
                'object_key' => $objectKey,
                'message' => $exception->getMessage(),
            ], 'operations');

            throw $exception;
        }
    }

    public function backupsPath(): string
    {
        return string_value(
            $this->config->get('backup.storage_path', $this->rootPath . '/storage/backups'),
            $this->rootPath . '/storage/backups'
        );
    }

    public function exportsPath(): string
    {
        return $this->backupsPath() . '/exports';
    }

    /**
     * @return list<BackupExportResult>
     */
    public function exports(): array
    {
        if (!is_dir($this->exportsPath())) {
            return [];
        }

        $entries = scandir($this->exportsPath());

        if ($entries === false) {
            return [];
        }

        /** @var array<string, int> $candidates */
        $candidates = [];

        foreach ($entries as $entry) {
            if (!is_string($entry) || !str_ends_with($entry, '.tar.gz.enc')) {
                continue;
            }

            $path = $this->exportsPath() . '/' . $entry;
            $candidates[$path] = filemtime($path) ?: 0;
        }

        arsort($candidates, SORT_NUMERIC);

        /** @var list<BackupExportResult> $exports */
        $exports = [];

        foreach (array_keys($candidates) as $candidate) {
            try {
                $exports[] = $this->exportResultFromPath($candidate);
            } catch (Throwable) {
                continue;
            }
        }

        return $exports;
    }

    private function exportPath(string $backupId): string
    {
        return sprintf(
            '%s/%s-%s-%s.tar.gz.enc',
            $this->exportsPath(),
            $backupId,
            date('Ymd-His'),
            bin2hex(random_bytes(2))
        );
    }

    /**
     * @return BackupExportResult|null
     */
    private function latestExportResult(string $backupId): ?array
    {
        if (!is_dir($this->exportsPath())) {
            return null;
        }

        $entries = scandir($this->exportsPath());

        if ($entries === false) {
            return null;
        }

        $candidates = [];

        foreach ($entries as $entry) {
            if (
                !is_string($entry)
                || !str_starts_with($entry, $backupId . '-')
                || !str_ends_with($entry, '.tar.gz.enc')
            ) {
                continue;
            }

            $path = $this->exportsPath() . '/' . $entry;
            $candidates[$path] = filemtime($path) ?: 0;
        }

        arsort($candidates, SORT_NUMERIC);

        foreach (array_keys($candidates) as $candidate) {
            try {
                $result = $this->exportResultFromPath($candidate);

                if ($result['backup_id'] === $backupId) {
                    return $result;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @return BackupExportResult
     */
    private function exportResultFromPath(string $exportPath): array
    {
        $envelope = $this->readExportEnvelope($exportPath);
        $payload = file_get_contents($exportPath);

        if ($payload === false) {
            throw new RuntimeException(sprintf('Unable to read backup export [%s].', $exportPath));
        }

        return [
            'backup_id' => $envelope['backup_id'],
            'export_path' => $exportPath,
            'archive_name' => $envelope['archive_name'],
            'archive_checksum' => $envelope['archive_checksum'],
            'encrypted_checksum' => hash('sha256', $payload),
            'created_at' => string_value($envelope['created_at'] ?? ''),
            'size_bytes' => filesize($exportPath) ?: 0,
        ];
    }

    private function exportCreatedAt(string $exportPath): string
    {
        $createdAt = $this->readExportEnvelope($exportPath)['created_at'];

        if ($createdAt === '') {
            throw new RuntimeException(sprintf('Backup export [%s] is missing a creation timestamp.', $exportPath));
        }

        return $createdAt;
    }

    private function temporaryPath(string $prefix): string
    {
        return sys_get_temp_dir() . '/sims-' . $prefix . '-' . bin2hex(random_bytes(4));
    }

    private function cleanupTemporaryPath(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $this->removeDirectory($path);
    }

    private function resolveExportPassphrase(?string $passphrase): string
    {
        $resolvedPassphrase = $passphrase ?? string_value($this->config->get('backup.export_key', ''));

        if ($resolvedPassphrase === '') {
            throw new RuntimeException('Backup export key is not configured. Set BACKUP_EXPORT_KEY or pass --passphrase=...');
        }

        return $resolvedPassphrase;
    }

    /**
     * @return BackupManifest
     */
    private function loadVerifiedManifest(string $backupId, string $failureMessage, bool $logSuccess = false): array
    {
        $manifest = $this->readManifest($backupId);

        try {
            $this->assertManifestIntegrity($manifest);
        } catch (Throwable $exception) {
            $this->logger->error($failureMessage, [
                'event' => 'backup.verify.failed',
                'backup_id' => $backupId,
                'message' => $exception->getMessage(),
            ], 'operations');

            throw $exception;
        }

        if ($logSuccess) {
            $this->logger->info('Backup verified.', [
                'event' => 'backup.verify.completed',
                'backup_id' => $backupId,
                'artifact_count' => $manifest['artifact_count'],
                'total_bytes' => $manifest['total_bytes'],
                'table_count' => $manifest['table_count'],
            ], 'operations');
        }

        return $manifest;
    }

    /**
     * @param BackupManifest $manifest
     */
    private function writeManifest(string $backupPath, array $manifest): void
    {
        $payload = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $written = file_put_contents($backupPath . '/manifest.json', $payload . PHP_EOL);

        if ($written === false) {
            throw new RuntimeException('Unable to write backup manifest.');
        }
    }

    /**
     * @return BackupManifest
     */
    private function readManifest(string $backupId): array
    {
        $backupPath = $this->backupPath($backupId);
        if (!is_dir($backupPath)) {
            throw new RuntimeException(sprintf('Backup [%s] was not found.', $backupId));
        }

        return $this->readManifestAtPath($backupPath);
    }

    /**
     * @return BackupManifest
     */
    private function readManifestAtPath(string $backupPath): array
    {
        $manifestPath = $backupPath . '/manifest.json';

        if (!is_file($manifestPath)) {
            throw new RuntimeException(sprintf('Backup manifest [%s] was not found.', $manifestPath));
        }

        $payload = file_get_contents($manifestPath);
        if ($payload === false) {
            throw new RuntimeException(sprintf('Unable to read manifest for backup [%s].', basename($backupPath)));
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Backup manifest for [%s] is invalid.', basename($backupPath)));
        }

        $databaseDetails = is_array($decoded['database'] ?? null) ? $decoded['database'] : [];
        $artifactItems = is_array($decoded['artifacts'] ?? null) ? $decoded['artifacts'] : [];

        /** @var list<string> $tables */
        $tables = strings_value($databaseDetails['tables'] ?? []);
        /** @var list<BackupArtifact> $artifacts */
        $artifacts = [];

        foreach ($artifactItems as $artifact) {
            if (!is_array($artifact)) {
                continue;
            }

            $fileItems = is_array($artifact['files'] ?? null) ? $artifact['files'] : [];
            /** @var list<BackupArtifactFile> $files */
            $files = [];

            foreach ($fileItems as $file) {
                if (!is_array($file)) {
                    continue;
                }

                $files[] = [
                    'relative_path' => string_value($file['relative_path'] ?? ''),
                    'path' => string_value($file['path'] ?? ''),
                    'checksum' => string_value($file['checksum'] ?? ''),
                    'size_bytes' => int_value($file['size_bytes'] ?? 0),
                ];
            }

            $artifacts[] = [
                'name' => string_value($artifact['name'] ?? ''),
                'kind' => string_value($artifact['kind'] ?? ''),
                'path' => string_value($artifact['path'] ?? ''),
                'source' => string_value($artifact['source'] ?? ''),
                'present' => bool_value($artifact['present'] ?? false),
                'size_bytes' => int_value($artifact['size_bytes'] ?? 0),
                'files' => $files,
            ];
        }

        return [
            'id' => string_value($decoded['id'] ?? ''),
            'created_at' => string_value($decoded['created_at'] ?? ''),
            'version' => nullable_string_value($decoded['version'] ?? null),
            'path' => string_value($decoded['path'] ?? $backupPath, $backupPath),
            'artifact_count' => int_value($decoded['artifact_count'] ?? 0),
            'total_bytes' => int_value($decoded['total_bytes'] ?? 0),
            'table_count' => int_value($decoded['table_count'] ?? count($tables)),
            'database' => [
                'driver' => string_value($databaseDetails['driver'] ?? ''),
                'target' => string_value($databaseDetails['target'] ?? ''),
                'dump_path' => string_value($databaseDetails['dump_path'] ?? $backupPath . '/database.sql', $backupPath . '/database.sql'),
                'tables' => $tables,
                'checksum' => string_value($databaseDetails['checksum'] ?? ''),
                'size_bytes' => int_value($databaseDetails['size_bytes'] ?? 0),
            ],
            'artifacts' => $artifacts,
        ];
    }

    private function backupPath(string $backupId): string
    {
        return $this->backupsPath() . '/' . $backupId;
    }

    private function appVersion(): ?string
    {
        $version = string_value($this->config->get('app.version', ''));

        return $version !== '' ? $version : null;
    }

    /**
     * @param BackupManifest $manifest
     * @return BackupManifest
     */
    private function normalizeManifestForCurrentRoot(array $manifest, string $backupPath): array
    {
        $manifest['path'] = $backupPath;
        $manifest['database']['dump_path'] = $backupPath . '/database.sql';

        foreach ($manifest['artifacts'] as &$artifact) {
            $artifact['source'] = $this->artifactSourcePath($artifact['name'], $artifact['source']);
            $artifact['path'] = $this->artifactBackupPath($backupPath, $artifact);

            foreach ($artifact['files'] as &$file) {
                $file['path'] = $artifact['kind'] === 'directory'
                    ? $artifact['path'] . '/' . $file['relative_path']
                    : $artifact['path'];
            }

            unset($file);
        }

        unset($artifact);

        return $manifest;
    }

    private function artifactSourcePath(string $artifactName, string $fallback): string
    {
        return match ($artifactName) {
            '.env' => $this->rootPath . '/.env',
            'private_uploads' => $this->rootPath . '/storage/app/private/uploads',
            'public_id_cards' => $this->rootPath . '/storage/app/public/id-cards',
            default => $fallback,
        };
    }

    /**
     * @param BackupArtifact $artifact
     */
    private function artifactBackupPath(string $backupPath, array $artifact): string
    {
        return match ($artifact['name']) {
            '.env' => $backupPath . '/files/.env',
            'private_uploads' => $backupPath . '/files/private-uploads',
            'public_id_cards' => $backupPath . '/files/public-id-cards',
            default => $backupPath . '/files/' . basename($artifact['path']),
        };
    }

    /**
     * @return BackupArtifactFile
     */
    private function fileDetails(string $path, string $relativePath): array
    {
        $payload = file_get_contents($path);

        if ($payload === false) {
            throw new RuntimeException(sprintf('Unable to read backup artifact [%s].', $path));
        }

        return [
            'relative_path' => $relativePath,
            'path' => $path,
            'checksum' => hash('sha256', $payload),
            'size_bytes' => strlen($payload),
        ];
    }

    /**
     * @return list<BackupArtifactFile>
     */
    private function directoryFiles(string $directory, string $relativePrefix = ''): array
    {
        $entries = scandir($directory);

        if ($entries === false) {
            throw new RuntimeException(sprintf('Unable to list directory [%s].', $directory));
        }

        /** @var list<BackupArtifactFile> $files */
        $files = [];

        foreach ($entries as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            $relativePath = $relativePrefix === '' ? $entry : $relativePrefix . '/' . $entry;

            if (is_dir($path)) {
                array_push($files, ...$this->directoryFiles($path, $relativePath));
                continue;
            }

            $files[] = $this->fileDetails($path, $relativePath);
        }

        return $files;
    }

    /**
     * @param list<BackupArtifactFile> $files
     */
    private function fileBytes(array $files): int
    {
        return array_reduce(
            $files,
            static fn (int $total, array $file): int => $total + $file['size_bytes'],
            0
        );
    }

    /**
     * @param list<BackupArtifact> $artifacts
     */
    private function artifactCount(array $artifacts): int
    {
        return array_reduce(
            $artifacts,
            static fn (int $total, array $artifact): int => $total + count($artifact['files']),
            0
        );
    }

    /**
     * @param list<BackupArtifact> $artifacts
     */
    private function artifactBytes(array $artifacts): int
    {
        return array_reduce(
            $artifacts,
            static fn (int $total, array $artifact): int => $total + $artifact['size_bytes'],
            0
        );
    }

    /**
     * @return BackupArtifact
     */
    private function snapshotFile(string $name, string $sourcePath, string $targetPath): array
    {
        if (is_file($sourcePath)) {
            $this->ensureDirectory(dirname($targetPath));

            if (!copy($sourcePath, $targetPath)) {
                throw new RuntimeException(sprintf('Unable to copy file backup source [%s].', $sourcePath));
            }
        }

        $files = is_file($sourcePath)
            ? [$this->fileDetails($targetPath, basename($targetPath))]
            : [];

        return [
            'name' => $name,
            'kind' => 'file',
            'path' => $targetPath,
            'source' => $sourcePath,
            'present' => is_file($sourcePath),
            'size_bytes' => $this->fileBytes($files),
            'files' => $files,
        ];
    }

    /**
     * @return BackupArtifact
     */
    private function snapshotDirectory(string $name, string $sourcePath, string $targetPath): array
    {
        $this->ensureDirectory($targetPath);

        if (is_dir($sourcePath)) {
            $this->copyDirectory($sourcePath, $targetPath);
        }

        $files = is_dir($sourcePath)
            ? $this->directoryFiles($targetPath)
            : [];

        return [
            'name' => $name,
            'kind' => 'directory',
            'path' => $targetPath,
            'source' => $sourcePath,
            'present' => is_dir($sourcePath),
            'size_bytes' => $this->fileBytes($files),
            'files' => $files,
        ];
    }

    /**
     * @return BackupExportEnvelope
     */
    private function encryptArchive(string $backupId, string $archiveName, string $archivePayload, string $passphrase): array
    {
        $salt = random_bytes(16);
        $iv = random_bytes(12);
        $key = $this->deriveExportKey($passphrase, $salt);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $archivePayload,
            self::EXPORT_CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if (!is_string($ciphertext)) {
            throw new RuntimeException(sprintf('Unable to encrypt archive for backup [%s].', $backupId));
        }

        /** @var string $tag */
        return [
            'version' => 1,
            'backup_id' => $backupId,
            'created_at' => date('c'),
            'cipher' => self::EXPORT_CIPHER,
            'kdf' => self::EXPORT_KDF,
            'iterations' => self::EXPORT_KDF_ITERATIONS,
            'archive_name' => $archiveName,
            'archive_checksum' => hash('sha256', $archivePayload),
            'salt' => base64_encode($salt),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ];
    }

    /**
     * @return BackupExportEnvelope
     */
    private function readExportEnvelope(string $archivePath): array
    {
        $payload = file_get_contents($archivePath);

        if ($payload === false) {
            throw new RuntimeException(sprintf('Unable to read backup export [%s].', $archivePath));
        }

        $decoded = json_decode($payload, true);

        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Backup export [%s] is invalid.', $archivePath));
        }

        return [
            'version' => int_value($decoded['version'] ?? 0),
            'backup_id' => string_value($decoded['backup_id'] ?? ''),
            'created_at' => string_value($decoded['created_at'] ?? ''),
            'cipher' => string_value($decoded['cipher'] ?? ''),
            'kdf' => string_value($decoded['kdf'] ?? ''),
            'iterations' => int_value($decoded['iterations'] ?? 0),
            'archive_name' => string_value($decoded['archive_name'] ?? ''),
            'archive_checksum' => string_value($decoded['archive_checksum'] ?? ''),
            'salt' => string_value($decoded['salt'] ?? ''),
            'iv' => string_value($decoded['iv'] ?? ''),
            'tag' => string_value($decoded['tag'] ?? ''),
            'ciphertext' => string_value($decoded['ciphertext'] ?? ''),
        ];
    }

    /**
     * @param BackupExportEnvelope $envelope
     */
    private function decryptArchive(array $envelope, string $passphrase): string
    {
        if (
            $envelope['version'] !== 1
            || $envelope['cipher'] !== self::EXPORT_CIPHER
            || $envelope['kdf'] !== self::EXPORT_KDF
            || $envelope['backup_id'] === ''
            || $envelope['archive_name'] === ''
            || $envelope['archive_checksum'] === ''
        ) {
            throw new RuntimeException('Backup export metadata is invalid.');
        }

        $salt = base64_decode($envelope['salt'], true);
        $iv = base64_decode($envelope['iv'], true);
        $tag = base64_decode($envelope['tag'], true);
        $ciphertext = base64_decode($envelope['ciphertext'], true);

        if (!is_string($salt) || !is_string($iv) || !is_string($tag) || !is_string($ciphertext)) {
            throw new RuntimeException(sprintf('Backup export for [%s] is malformed.', $envelope['backup_id']));
        }

        $key = $this->deriveExportKey($passphrase, $salt, $envelope['iterations']);
        $archivePayload = openssl_decrypt(
            $ciphertext,
            self::EXPORT_CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if (!is_string($archivePayload)) {
            throw new RuntimeException(sprintf('Unable to decrypt backup export for [%s].', $envelope['backup_id']));
        }

        if (hash('sha256', $archivePayload) !== $envelope['archive_checksum']) {
            throw new RuntimeException(sprintf('Backup export checksum mismatch for [%s].', $envelope['backup_id']));
        }

        return $archivePayload;
    }

    private function deriveExportKey(string $passphrase, string $salt, ?int $iterations = null): string
    {
        $resolvedIterations = $iterations ?? self::EXPORT_KDF_ITERATIONS;

        if ($resolvedIterations < 1) {
            throw new RuntimeException('Backup export key derivation iterations must be greater than zero.');
        }

        $key = hash_pbkdf2('sha256', $passphrase, $salt, $resolvedIterations, 32, true);

        return $key;
    }

    private function createArchive(string $backupPath, string $workingPath): string
    {
        $archivePath = $workingPath . '/' . basename($backupPath) . '.tar';
        $backupId = basename($backupPath);
        $archive = new PharData($archivePath);
        $archive->addEmptyDir($backupId);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backupPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            /** @var \SplFileInfo $entry */
            $localPath = $backupId . '/' . substr($entry->getPathname(), strlen($backupPath) + 1);

            if ($entry->isDir()) {
                $archive->addEmptyDir($localPath);
                continue;
            }

            $archive->addFile($entry->getPathname(), $localPath);
        }

        $archive->compress(Phar::GZ);
        unset($archive);

        if (!unlink($archivePath)) {
            throw new RuntimeException(sprintf('Unable to finalize archive for backup [%s].', $backupId));
        }

        $compressedArchivePath = $archivePath . '.gz';

        if (!is_file($compressedArchivePath)) {
            throw new RuntimeException(sprintf('Compressed archive for backup [%s] is missing.', $backupId));
        }

        return $compressedArchivePath;
    }

    private function extractArchive(string $archivePath, string $targetPath): string
    {
        $compressedArchive = new PharData($archivePath);
        $compressedArchive->decompress();
        $tarPath = substr($archivePath, 0, -3);

        if (!is_file($tarPath)) {
            throw new RuntimeException(sprintf('Backup archive [%s] could not be decompressed.', $archivePath));
        }

        $archive = new PharData($tarPath);
        $archive->extractTo($targetPath, overwrite: true);

        $entries = scandir($targetPath);

        if ($entries === false) {
            throw new RuntimeException(sprintf('Unable to inspect extracted backup archive [%s].', $archivePath));
        }

        $directories = [];

        foreach ($entries as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }

            $path = $targetPath . '/' . $entry;

            if (is_dir($path)) {
                $directories[] = $path;
            }
        }

        if (count($directories) !== 1) {
            throw new RuntimeException(sprintf('Backup archive [%s] does not contain exactly one backup directory.', $archivePath));
        }

        return $directories[0];
    }

    /**
     * @param BackupManifest $manifest
     */
    private function assertManifestIntegrity(array $manifest): void
    {
        $this->assertDatabaseDumpIntegrity($manifest);

        $artifactCount = 0;
        $artifactBytes = 0;

        foreach ($manifest['artifacts'] as $artifact) {
            $this->assertArtifactIntegrity($manifest['id'], $artifact);
            $artifactCount += count($artifact['files']);
            $artifactBytes += $artifact['size_bytes'];
        }

        if ($manifest['artifact_count'] !== $artifactCount) {
            throw new RuntimeException(sprintf(
                'Backup [%s] artifact count does not match the manifest.',
                $manifest['id']
            ));
        }

        if ($manifest['total_bytes'] !== $manifest['database']['size_bytes'] + $artifactBytes) {
            throw new RuntimeException(sprintf(
                'Backup [%s] total bytes do not match the manifest.',
                $manifest['id']
            ));
        }

        if ($manifest['table_count'] !== count($manifest['database']['tables'])) {
            throw new RuntimeException(sprintf(
                'Backup [%s] table count does not match the manifest.',
                $manifest['id']
            ));
        }
    }

    /**
     * @param BackupManifest $manifest
     */
    private function assertDatabaseDumpIntegrity(array $manifest): void
    {
        $dumpPath = $manifest['database']['dump_path'];

        if (!is_file($dumpPath)) {
            throw new RuntimeException(sprintf('Database dump for backup [%s] is missing.', $manifest['id']));
        }

        if ($manifest['database']['checksum'] === '') {
            throw new RuntimeException(sprintf('Database dump checksum is missing for backup [%s].', $manifest['id']));
        }

        $details = $this->fileDetails($dumpPath, 'database.sql');

        if ($details['checksum'] !== $manifest['database']['checksum']) {
            throw new RuntimeException(sprintf('Database dump checksum mismatch for backup [%s].', $manifest['id']));
        }

        if ($details['size_bytes'] !== $manifest['database']['size_bytes']) {
            throw new RuntimeException(sprintf('Database dump size mismatch for backup [%s].', $manifest['id']));
        }
    }

    /**
     * @param BackupArtifact $artifact
     */
    private function assertArtifactIntegrity(string $backupId, array $artifact): void
    {
        if ($artifact['kind'] !== 'file' && $artifact['kind'] !== 'directory') {
            throw new RuntimeException(sprintf(
                'Backup [%s] contains an invalid artifact type for [%s].',
                $backupId,
                $artifact['name']
            ));
        }

        if ($artifact['kind'] === 'file') {
            if (!$artifact['present']) {
                if ($artifact['files'] !== [] || $artifact['size_bytes'] !== 0) {
                    throw new RuntimeException(sprintf(
                        'Backup [%s] has invalid metadata for missing file artifact [%s].',
                        $backupId,
                        $artifact['name']
                    ));
                }

                return;
            }

            if (!is_file($artifact['path'])) {
                throw new RuntimeException(sprintf('Backup file artifact [%s] is missing.', $artifact['name']));
            }

            if (count($artifact['files']) !== 1) {
                throw new RuntimeException(sprintf(
                    'Backup [%s] must track exactly one file for artifact [%s].',
                    $backupId,
                    $artifact['name']
                ));
            }
        } else {
            if (!is_dir($artifact['path'])) {
                throw new RuntimeException(sprintf('Backup directory artifact [%s] is missing.', $artifact['name']));
            }
        }

        $totalBytes = 0;

        foreach ($artifact['files'] as $file) {
            $this->assertArtifactFileIntegrity($backupId, $artifact['name'], $file);
            $totalBytes += $file['size_bytes'];
        }

        if ($totalBytes !== $artifact['size_bytes']) {
            throw new RuntimeException(sprintf(
                'Backup [%s] size mismatch for artifact [%s].',
                $backupId,
                $artifact['name']
            ));
        }
    }

    /**
     * @param BackupArtifactFile $file
     */
    private function assertArtifactFileIntegrity(string $backupId, string $artifactName, array $file): void
    {
        if ($file['checksum'] === '') {
            throw new RuntimeException(sprintf(
                'Backup [%s] checksum is missing for artifact [%s].',
                $backupId,
                $artifactName
            ));
        }

        if (!is_file($file['path'])) {
            throw new RuntimeException(sprintf(
                'Backup file artifact [%s] is missing.',
                $artifactName
            ));
        }

        $details = $this->fileDetails($file['path'], $file['relative_path']);

        if ($details['checksum'] !== $file['checksum']) {
            throw new RuntimeException(sprintf(
                'Backup checksum mismatch for artifact [%s] file [%s].',
                $artifactName,
                $file['relative_path']
            ));
        }

        if ($details['size_bytes'] !== $file['size_bytes']) {
            throw new RuntimeException(sprintf(
                'Backup size mismatch for artifact [%s] file [%s].',
                $artifactName,
                $file['relative_path']
            ));
        }
    }

    /**
     * @param BackupManifest $manifest
     */
    private function restoreArtifacts(array $manifest): void
    {
        foreach ($manifest['artifacts'] as $artifact) {
            if ($artifact['kind'] === 'file') {
                $this->restoreFileArtifact($artifact);
                continue;
            }

            $this->restoreDirectoryArtifact($artifact);
        }
    }

    /**
     * @param BackupArtifact $artifact
     */
    private function restoreFileArtifact(array $artifact): void
    {
        if (!$artifact['present']) {
            return;
        }

        if (!is_file($artifact['path'])) {
            throw new RuntimeException(sprintf('Backup file artifact [%s] is missing.', $artifact['name']));
        }

        $this->ensureDirectory(dirname($artifact['source']));

        if (!copy($artifact['path'], $artifact['source'])) {
            throw new RuntimeException(sprintf('Unable to restore file artifact [%s].', $artifact['name']));
        }
    }

    /**
     * @param BackupArtifact $artifact
     */
    private function restoreDirectoryArtifact(array $artifact): void
    {
        if (!is_dir($artifact['path'])) {
            throw new RuntimeException(sprintf('Backup directory artifact [%s] is missing.', $artifact['name']));
        }

        $this->clearDirectory($artifact['source']);
        $this->copyDirectory($artifact['path'], $artifact['source']);
    }

    private function dumpDatabase(PDO $database): string
    {
        $driver = $this->driverName($database);
        $statements = [];

        foreach ($this->tables($database, $driver) as $table) {
            $statements[] = $this->dropTableStatement($driver, $table);
            $statements[] = $this->createTableStatement($database, $driver, $table);

            foreach ($this->tableRows($database, $driver, $table) as $row) {
                $statements[] = $this->insertStatement($database, $driver, $table, $row);
            }
        }

        return implode(self::STATEMENT_SEPARATOR, $statements);
    }

    /**
     * @return list<string>
     */
    private function tables(PDO $database, string $driver): array
    {
        if ($driver === 'sqlite') {
            $statement = $database->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name ASC"
            );
            if (!$statement instanceof \PDOStatement) {
                throw new RuntimeException('Unable to list SQLite tables.');
            }
            $tables = $statement->fetchAll(PDO::FETCH_COLUMN);

            return array_values(array_filter($tables, static fn (mixed $table): bool => is_string($table) && $table !== ''));
        }

        $statement = $database->query('SHOW TABLES');
        if (!$statement instanceof \PDOStatement) {
            throw new RuntimeException('Unable to list MySQL tables.');
        }
        $tables = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter($tables, static fn (mixed $table): bool => is_string($table) && $table !== ''));
    }

    private function driverName(PDO $database): string
    {
        return string_value($database->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql');
    }

    private function dropTableStatement(string $driver, string $table): string
    {
        return sprintf('DROP TABLE IF EXISTS %s', $this->quoteIdentifier($driver, $table));
    }

    private function createTableStatement(PDO $database, string $driver, string $table): string
    {
        if ($driver === 'sqlite') {
            $statement = $database->prepare(
                "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = :name LIMIT 1"
            );
            $statement->execute(['name' => $table]);
            $sql = $statement->fetchColumn();

            if (!is_string($sql) || trim($sql) === '') {
                throw new RuntimeException(sprintf('Unable to resolve create statement for table [%s].', $table));
            }

            return $sql;
        }

        $statement = $database->query(sprintf('SHOW CREATE TABLE %s', $this->quoteIdentifier($driver, $table)));
        if (!$statement instanceof \PDOStatement) {
            throw new RuntimeException(sprintf('Unable to resolve create statement for table [%s].', $table));
        }
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new RuntimeException(sprintf('Unable to resolve create statement for table [%s].', $table));
        }

        foreach ($row as $key => $value) {
            if (str_starts_with(strtolower((string) $key), 'create table') && is_string($value) && $value !== '') {
                return $value;
            }
        }

        $values = array_values($row);
        $create = $values[1] ?? null;

        if (!is_string($create) || $create === '') {
            throw new RuntimeException(sprintf('Unable to resolve create statement for table [%s].', $table));
        }

        return $create;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tableRows(PDO $database, string $driver, string $table): array
    {
        $statement = $database->query(sprintf('SELECT * FROM %s', $this->quoteIdentifier($driver, $table)));
        if (!$statement instanceof \PDOStatement) {
            throw new RuntimeException(sprintf('Unable to read rows for table [%s].', $table));
        }

        return rows_value($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insertStatement(PDO $database, string $driver, string $table, array $row): string
    {
        $columns = [];
        $values = [];

        foreach ($row as $column => $value) {
            $columns[] = $this->quoteIdentifier($driver, (string) $column);
            $values[] = $this->sqlValue($database, $value);
        }

        return sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($driver, $table),
            implode(', ', $columns),
            implode(', ', $values)
        );
    }

    private function quoteIdentifier(string $driver, string $identifier): string
    {
        if ($driver === 'mysql') {
            return '`' . str_replace('`', '``', $identifier) . '`';
        }

        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function sqlValue(PDO $database, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $quoted = $database->quote(string_value($value));

        if (!is_string($quoted)) {
            throw new RuntimeException('Unable to quote database backup value.');
        }

        return $quoted;
    }

    /**
     * @param BackupManifest $manifest
     */
    private function restoreDatabase(PDO $database, array $manifest): void
    {
        $dumpPath = $manifest['database']['dump_path'];
        if (!is_file($dumpPath)) {
            throw new RuntimeException(sprintf('Database dump for backup [%s] is missing.', $manifest['id']));
        }

        $payload = file_get_contents($dumpPath);
        if ($payload === false) {
            throw new RuntimeException(sprintf('Unable to read database dump for backup [%s].', $manifest['id']));
        }

        $driver = $this->driverName($database);
        $statements = $this->splitDumpStatements($payload);

        if ($driver === 'sqlite') {
            $database->exec('PRAGMA foreign_keys = OFF');
        } else {
            $database->exec('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            $database->beginTransaction();

            foreach ($this->tables($database, $driver) as $table) {
                $database->exec($this->dropTableStatement($driver, $table));
            }

            foreach ($statements as $statement) {
                $database->exec($statement);
            }

            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }

            throw new RuntimeException('Unable to restore database backup.', 0, $exception);
        } finally {
            if ($driver === 'sqlite') {
                $database->exec('PRAGMA foreign_keys = ON');
            } else {
                $database->exec('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    /**
     * @return list<string>
     */
    private function splitDumpStatements(string $payload): array
    {
        if ($payload === '') {
            return [];
        }

        $statements = array_map(
            static fn (string $statement): string => trim($statement),
            explode(self::STATEMENT_SEPARATOR, $payload)
        );

        return array_values(array_filter($statements, static fn (string $statement): bool => $statement !== ''));
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create directory [%s].', $path));
        }
    }

    private function copyDirectory(string $source, string $target): void
    {
        $this->ensureDirectory($target);

        $entries = scandir($source);
        if ($entries === false) {
            throw new RuntimeException(sprintf('Unable to list directory [%s].', $source));
        }

        foreach ($entries as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }

            $sourcePath = $source . '/' . $entry;
            $targetPath = $target . '/' . $entry;

            if (is_dir($sourcePath)) {
                $this->copyDirectory($sourcePath, $targetPath);
                continue;
            }

            if (!copy($sourcePath, $targetPath)) {
                throw new RuntimeException(sprintf('Unable to copy artifact [%s].', $sourcePath));
            }
        }
    }

    private function clearDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            $this->ensureDirectory($directory);

            return;
        }

        $entries = scandir($directory);
        if ($entries === false) {
            throw new RuntimeException(sprintf('Unable to list directory [%s].', $directory));
        }

        foreach ($entries as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            if (!unlink($path)) {
                throw new RuntimeException(sprintf('Unable to remove file [%s].', $path));
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        $entries = scandir($directory);
        if ($entries === false) {
            throw new RuntimeException(sprintf('Unable to list directory [%s].', $directory));
        }

        foreach ($entries as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            if (!unlink($path)) {
                throw new RuntimeException(sprintf('Unable to remove file [%s].', $path));
            }
        }

        if (!rmdir($directory)) {
            throw new RuntimeException(sprintf('Unable to remove directory [%s].', $directory));
        }
    }
}
