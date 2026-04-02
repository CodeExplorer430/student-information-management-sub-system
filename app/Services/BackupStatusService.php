<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\RequestContext;
use Throwable;

/**
 * @phpstan-type BackupStatusCheck array{name: string, status: string, message: string}
 * @phpstan-type BackupStatusLatest array{
 *     backup_id: string|null,
 *     created_at: string|null,
 *     age_hours: float|null,
 *     location: string|null
 * }
 * @phpstan-type BackupStatusReport array{
 *     status: string,
 *     timestamp: string,
 *     request_id: string,
 *     counts: array{local: int, remote: int},
 *     thresholds: array{
 *         local_hours: int,
 *         remote_hours: int,
 *         drill_hours: int,
 *         remote_enabled: bool
 *     },
 *     latest: array{
 *         local_backup: BackupStatusLatest,
 *         verified_backup: BackupStatusLatest,
 *         export: BackupStatusLatest,
 *         remote_push: BackupStatusLatest,
 *         drill: BackupStatusLatest
 *     },
 *     checks: list<BackupStatusCheck>
 * }
 */
final class BackupStatusService
{
    public function __construct(
        private readonly BackupService $backups,
        private readonly Config $config,
        private readonly Logger $logger,
        private readonly RequestContext $requestContext
    ) {
    }

    /**
     * @return BackupStatusReport
     */
    public function report(): array
    {
        $localThreshold = int_value($this->config->get('backup.max_age_hours', 24), 24);
        $remoteThreshold = int_value($this->config->get('backup.remote_max_age_hours', 24), 24);
        $drillThreshold = int_value($this->config->get('backup.drill_max_age_hours', 168), 168);
        $remoteEnabled = string_value($this->config->get('backup.remote.driver', '')) !== '';

        $localBackups = $this->backups->list();
        $exports = $this->backups->exports();
        $operations = $this->logger->entries(null, 'operations');

        $remoteBackups = [];
        $remoteFailure = null;

        if ($remoteEnabled) {
            try {
                $remoteBackups = $this->backups->remoteList();
            } catch (Throwable $exception) {
                $remoteFailure = $exception->getMessage();
            }
        }

        $latestLocal = $this->latestLocalBackup($localBackups);
        $latestVerified = $this->latestOperation($operations, 'backup.verify.completed', 'Backup verified.');
        $latestExport = $this->latestExport($exports);
        $latestRemote = $this->latestRemoteBackup($remoteBackups);
        $latestDrill = $this->latestOperation($operations, 'backup.drill.completed', 'Backup drill completed.');

        /** @var list<BackupStatusCheck> $checks */
        $checks = [];
        $checks[] = $this->localBackupCheck($latestLocal, $localThreshold);
        $checks[] = $this->verificationCheck($latestLocal, $latestVerified);
        $checks[] = $this->remoteBackupCheck($latestRemote, $remoteThreshold, $remoteEnabled, $remoteFailure);
        $checks[] = $this->drillCheck($latestDrill, $drillThreshold);

        $status = 'pass';

        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $status = 'fail';
                break;
            }
        }

        return [
            'status' => $status,
            'timestamp' => date('c'),
            'request_id' => $this->requestContext->requestId(),
            'counts' => [
                'local' => count($localBackups),
                'remote' => count($remoteBackups),
            ],
            'thresholds' => [
                'local_hours' => $localThreshold,
                'remote_hours' => $remoteThreshold,
                'drill_hours' => $drillThreshold,
                'remote_enabled' => $remoteEnabled,
            ],
            'latest' => [
                'local_backup' => $latestLocal,
                'verified_backup' => $latestVerified,
                'export' => $latestExport,
                'remote_push' => $latestRemote,
                'drill' => $latestDrill,
            ],
            'checks' => $checks,
        ];
    }

    /**
     * @param list<array<string, mixed>> $backups
     * @return BackupStatusLatest
     */
    private function latestLocalBackup(array $backups): array
    {
        $backup = $backups[0] ?? null;

        if (!is_array($backup)) {
            return $this->emptyLatest();
        }

        $createdAt = string_value($backup['created_at'] ?? '');

        return [
            'backup_id' => string_value($backup['id'] ?? '') ?: null,
            'created_at' => $createdAt !== '' ? $createdAt : null,
            'age_hours' => $this->ageHours($createdAt),
            'location' => string_value($backup['path'] ?? '') ?: null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $exports
     * @return BackupStatusLatest
     */
    private function latestExport(array $exports): array
    {
        $export = $exports[0] ?? null;

        if (!is_array($export)) {
            return $this->emptyLatest();
        }

        $createdAt = string_value($export['created_at'] ?? '');

        return [
            'backup_id' => string_value($export['backup_id'] ?? '') ?: null,
            'created_at' => $createdAt !== '' ? $createdAt : null,
            'age_hours' => $this->ageHours($createdAt),
            'location' => string_value($export['export_path'] ?? '') ?: null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $remoteBackups
     * @return BackupStatusLatest
     */
    private function latestRemoteBackup(array $remoteBackups): array
    {
        $remote = $remoteBackups[0] ?? null;

        if (!is_array($remote)) {
            return $this->emptyLatest();
        }

        $createdAt = string_value($remote['created_at'] ?? '');

        return [
            'backup_id' => string_value($remote['backup_id'] ?? '') ?: null,
            'created_at' => $createdAt !== '' ? $createdAt : null,
            'age_hours' => $this->ageHours($createdAt),
            'location' => string_value($remote['object_key'] ?? '') ?: null,
        ];
    }

    /**
     * @param list<array{
     *     timestamp: string,
     *     level: string,
     *     channel: string,
     *     request_id: string,
     *     message: string,
     *     context: array<string, mixed>
     * }> $entries
     * @return BackupStatusLatest
     */
    private function latestOperation(array $entries, string $event, string $fallbackMessage): array
    {
        foreach ($entries as $entry) {
            $context = map_value($entry['context']);
            $loggedEvent = string_value($context['event'] ?? '');

            if ($loggedEvent !== $event && $entry['message'] !== $fallbackMessage) {
                continue;
            }

            $timestamp = string_value($entry['timestamp'] ?? '');

            return [
                'backup_id' => string_value($context['backup_id'] ?? '') ?: null,
                'created_at' => $timestamp !== '' ? $timestamp : null,
                'age_hours' => $this->ageHours($timestamp),
                'location' => null,
            ];
        }

        return $this->emptyLatest();
    }

    /**
     * @param BackupStatusLatest $latestLocal
     * @return BackupStatusCheck
     */
    private function localBackupCheck(array $latestLocal, int $thresholdHours): array
    {
        if ($latestLocal['backup_id'] === null || $latestLocal['age_hours'] === null) {
            return [
                'name' => 'local_backup_recency',
                'status' => 'fail',
                'message' => sprintf('No local backup found within the required %d-hour window.', $thresholdHours),
            ];
        }

        if ($latestLocal['age_hours'] > $thresholdHours) {
            return [
                'name' => 'local_backup_recency',
                'status' => 'fail',
                'message' => sprintf(
                    'Latest local backup [%s] is %.2f hours old, exceeding the %d-hour limit.',
                    string_value($latestLocal['backup_id']),
                    $latestLocal['age_hours'],
                    $thresholdHours
                ),
            ];
        }

        return [
            'name' => 'local_backup_recency',
            'status' => 'pass',
            'message' => sprintf(
                'Latest local backup [%s] is %.2f hours old.',
                string_value($latestLocal['backup_id']),
                $latestLocal['age_hours']
            ),
        ];
    }

    /**
     * @param BackupStatusLatest $latestLocal
     * @param BackupStatusLatest $latestVerified
     * @return BackupStatusCheck
     */
    private function verificationCheck(array $latestLocal, array $latestVerified): array
    {
        if ($latestLocal['backup_id'] === null) {
            return [
                'name' => 'local_backup_verification',
                'status' => 'fail',
                'message' => 'No local backup is available to verify.',
            ];
        }

        if ($latestVerified['backup_id'] === null) {
            return [
                'name' => 'local_backup_verification',
                'status' => 'fail',
                'message' => sprintf('Latest local backup [%s] has not been verified.', string_value($latestLocal['backup_id'])),
            ];
        }

        if ($latestVerified['backup_id'] !== $latestLocal['backup_id']) {
            return [
                'name' => 'local_backup_verification',
                'status' => 'fail',
                'message' => sprintf(
                    'Latest verified backup [%s] does not match latest local backup [%s].',
                    string_value($latestVerified['backup_id']),
                    string_value($latestLocal['backup_id'])
                ),
            ];
        }

        return [
            'name' => 'local_backup_verification',
            'status' => 'pass',
            'message' => sprintf('Latest local backup [%s] has a successful verification record.', string_value($latestLocal['backup_id'])),
        ];
    }

    /**
     * @param BackupStatusLatest $latestRemote
     * @return BackupStatusCheck
     */
    private function remoteBackupCheck(array $latestRemote, int $thresholdHours, bool $remoteEnabled, ?string $remoteFailure): array
    {
        if (!$remoteEnabled) {
            return [
                'name' => 'remote_backup_recency',
                'status' => 'pass',
                'message' => 'Remote backup replication is disabled in configuration.',
            ];
        }

        if ($remoteFailure !== null) {
            return [
                'name' => 'remote_backup_recency',
                'status' => 'fail',
                'message' => 'Remote backup status could not be determined: ' . $remoteFailure,
            ];
        }

        if ($latestRemote['backup_id'] === null || $latestRemote['age_hours'] === null) {
            return [
                'name' => 'remote_backup_recency',
                'status' => 'fail',
                'message' => sprintf('No remote backup push found within the required %d-hour window.', $thresholdHours),
            ];
        }

        if ($latestRemote['age_hours'] > $thresholdHours) {
            return [
                'name' => 'remote_backup_recency',
                'status' => 'fail',
                'message' => sprintf(
                    'Latest remote backup [%s] is %.2f hours old, exceeding the %d-hour limit.',
                    string_value($latestRemote['backup_id']),
                    $latestRemote['age_hours'],
                    $thresholdHours
                ),
            ];
        }

        return [
            'name' => 'remote_backup_recency',
            'status' => 'pass',
            'message' => sprintf(
                'Latest remote backup [%s] is %.2f hours old.',
                string_value($latestRemote['backup_id']),
                $latestRemote['age_hours']
            ),
        ];
    }

    /**
     * @param BackupStatusLatest $latestDrill
     * @return BackupStatusCheck
     */
    private function drillCheck(array $latestDrill, int $thresholdHours): array
    {
        if ($latestDrill['backup_id'] === null || $latestDrill['age_hours'] === null) {
            return [
                'name' => 'backup_drill_recency',
                'status' => 'fail',
                'message' => sprintf('No backup drill found within the required %d-hour window.', $thresholdHours),
            ];
        }

        if ($latestDrill['age_hours'] > $thresholdHours) {
            return [
                'name' => 'backup_drill_recency',
                'status' => 'fail',
                'message' => sprintf(
                    'Latest backup drill for [%s] is %.2f hours old, exceeding the %d-hour limit.',
                    string_value($latestDrill['backup_id']),
                    $latestDrill['age_hours'],
                    $thresholdHours
                ),
            ];
        }

        return [
            'name' => 'backup_drill_recency',
            'status' => 'pass',
            'message' => sprintf(
                'Latest backup drill for [%s] is %.2f hours old.',
                string_value($latestDrill['backup_id']),
                $latestDrill['age_hours']
            ),
        ];
    }

    /**
     * @return BackupStatusLatest
     */
    private function emptyLatest(): array
    {
        return [
            'backup_id' => null,
            'created_at' => null,
            'age_hours' => null,
            'location' => null,
        ];
    }

    private function ageHours(?string $timestamp): ?float
    {
        if ($timestamp === null || $timestamp === '') {
            return null;
        }

        $unixTime = strtotime($timestamp);

        if ($unixTime === false) {
            return null;
        }

        return round(max(0, time() - $unixTime) / 3600, 2);
    }
}
