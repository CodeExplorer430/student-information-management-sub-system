<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\RequestContext;

/**
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
 *         local_backup: array{backup_id: string|null, created_at: string|null, age_hours: float|null, location: string|null},
 *         verified_backup: array{backup_id: string|null, created_at: string|null, age_hours: float|null, location: string|null},
 *         export: array{backup_id: string|null, created_at: string|null, age_hours: float|null, location: string|null},
 *         remote_push: array{backup_id: string|null, created_at: string|null, age_hours: float|null, location: string|null},
 *         drill: array{backup_id: string|null, created_at: string|null, age_hours: float|null, location: string|null}
 *     },
 *     checks: list<array{name: string, status: string, message: string}>
 * }
 * @phpstan-type OpsLogEntry array{
 *     timestamp: string,
 *     level: string,
 *     channel: string,
 *     request_id: string,
 *     message: string,
 *     context: array<string, mixed>
 * }
 * @phpstan-type OpsAlert array{
 *     key: string,
 *     severity: string,
 *     status: string,
 *     message: string,
 *     remediation: string,
 *     detected_at: string,
 *     last_seen_at: string,
 *     request_id: string
 * }
 * @phpstan-type OpsAlertReport array{
 *     status: string,
 *     timestamp: string,
 *     request_id: string,
 *     counts: array{active: int, notified: int, resolved: int},
 *     active_alerts: list<OpsAlert>,
 *     notified_keys: list<string>,
 *     resolved_keys: list<string>
 * }
 */
final class OpsAlertService
{
    public function __construct(
        private readonly BackupStatusService $backupStatus,
        private readonly NotificationService $notifications,
        private readonly Logger $logger,
        private readonly RequestContext $requestContext
    ) {
    }

    /**
     * @return OpsAlertReport
     */
    public function report(): array
    {
        return $this->buildReport(
            $this->currentTimestamp(),
            $this->logger->entries(null, 'operations'),
            [],
            []
        );
    }

    /**
     * @return OpsAlertReport
     */
    public function checkAndDispatch(): array
    {
        $timestamp = $this->currentTimestamp();
        $operations = $this->logger->entries(null, 'operations');
        $baseReport = $this->buildReport($timestamp, $operations, [], []);
        $activeAlerts = $baseReport['active_alerts'];
        $cycles = $this->alertNotificationCycles($operations);

        /** @var list<string> $notified */
        $notified = [];
        /** @var list<string> $resolved */
        $resolved = [];

        foreach ($activeAlerts as $alert) {
            $cycle = $cycles[$alert['key']] ?? ['active' => false, 'first_detected_at' => null];

            if ($cycle['active']) {
                continue;
            }

            $this->notifications->notifyPermissionRecipients(
                'admin.roles',
                'ops_alert',
                $this->alertEntityId($alert['key']),
                'Operational alert: ' . $alert['key'],
                $alert['message'] . ' Remediation: ' . $alert['remediation']
            );

            $this->logger->error('Operational alert dispatched.', [
                'event' => 'ops.alert.sent',
                'alert_key' => $alert['key'],
                'severity' => $alert['severity'],
                'status' => $alert['status'],
                'detected_at' => $alert['detected_at'],
            ], 'operations');
            $notified[] = $alert['key'];
        }

        $activeKeys = array_map(
            static fn (array $alert): string => string_value($alert['key']),
            $activeAlerts
        );

        foreach ($cycles as $key => $cycle) {
            if (!$cycle['active'] || in_array($key, $activeKeys, true)) {
                continue;
            }

            $this->logger->info('Operational alert resolved.', [
                'event' => 'ops.alert.resolved',
                'alert_key' => $key,
            ], 'operations');
            $resolved[] = $key;
        }

        return $this->buildReport($timestamp, $operations, $notified, $resolved);
    }

    /**
     * @param list<OpsLogEntry> $operations
     * @param list<string> $notified
     * @param list<string> $resolved
     * @return OpsAlertReport
     */
    private function buildReport(string $timestamp, array $operations, array $notified, array $resolved): array
    {
        /** @var BackupStatusReport $backup */
        $backup = $this->backupStatus->report();

        $activeAlerts = [
            ...$this->backupAlerts($backup, $operations, $timestamp),
            ...$this->operationFailureAlerts($operations),
        ];

        usort(
            $activeAlerts,
            static fn (array $left, array $right): int => strcmp(
                string_value($left['key']),
                string_value($right['key'])
            )
        );

        return [
            'status' => $activeAlerts === [] ? 'pass' : 'fail',
            'timestamp' => $timestamp,
            'request_id' => $this->requestContext->requestId(),
            'counts' => [
                'active' => count($activeAlerts),
                'notified' => count($notified),
                'resolved' => count($resolved),
            ],
            'active_alerts' => $activeAlerts,
            'notified_keys' => $notified,
            'resolved_keys' => $resolved,
        ];
    }

    /**
     * @param BackupStatusReport $backup
     * @param list<OpsLogEntry> $operations
     * @return list<OpsAlert>
     */
    private function backupAlerts(array $backup, array $operations, string $timestamp): array
    {
        $definitions = [
            'local_backup_recency' => [
                'key' => 'backup.local.stale',
                'message' => 'Local backup freshness has exceeded the configured threshold.',
                'remediation' => 'Run php bin/console backup:run and confirm backup:status returns pass.',
                'latest_key' => 'local_backup',
            ],
            'local_backup_verification' => [
                'key' => 'backup.local.unverified',
                'message' => 'Latest local backup is missing verification coverage.',
                'remediation' => 'Run php bin/console backup:verify <backup-id> and re-run php bin/console backup:status.',
                'latest_key' => 'verified_backup',
            ],
            'remote_backup_recency' => [
                'key' => 'backup.remote.stale',
                'message' => 'Remote backup replication is stale or unavailable.',
                'remediation' => 'Check remote backup connectivity, then run php bin/console backup:push <backup-id> or php bin/console backup:run --push-remote.',
                'latest_key' => 'remote_push',
            ],
            'backup_drill_recency' => [
                'key' => 'backup.drill.stale',
                'message' => 'Restore drill coverage is stale or missing.',
                'remediation' => 'Run php bin/console backup:drill <backup-id> and confirm backup:status returns pass.',
                'latest_key' => 'drill',
            ],
        ];

        $cycles = $this->alertNotificationCycles($operations);
        /** @var list<OpsAlert> $alerts */
        $alerts = [];

        foreach ($backup['checks'] as $check) {
            if ($check['status'] !== 'fail') {
                continue;
            }

            $definition = $definitions[$check['name']] ?? null;

            if (!is_array($definition)) {
                continue;
            }

            $latest = $backup['latest'][$definition['latest_key']] ?? null;
            $detectedAt = is_array($latest)
                ? string_value($latest['created_at'] ?? '')
                : '';
            $cycle = $cycles[$definition['key']] ?? ['active' => false, 'first_detected_at' => null];
            $firstDetectedAt = string_value($cycle['first_detected_at'] ?? '');

            $alerts[] = $this->alert(
                $definition['key'],
                $definition['message'] . ' ' . $check['message'],
                $definition['remediation'],
                $firstDetectedAt !== '' ? $firstDetectedAt : ($detectedAt !== '' ? $detectedAt : $timestamp),
                $timestamp
            );
        }

        return $alerts;
    }

    /**
     * @param list<OpsLogEntry> $operations
     * @return list<OpsAlert>
     */
    private function operationFailureAlerts(array $operations): array
    {
        $definitions = [
            [
                'failure_event' => 'backup.run.failed',
                'success_events' => ['backup.run.completed'],
                'key' => 'backup.run.failed',
                'message' => 'The most recent automated backup run failed.',
                'remediation' => 'Inspect operations logs, fix the failing stage, then rerun php bin/console backup:run.',
            ],
            [
                'failure_event' => 'release.failed',
                'success_events' => ['release.completed'],
                'key' => 'release.failed',
                'message' => 'The most recent release-check workflow failed.',
                'remediation' => 'Review the failing release stage and follow the documented rollback or retry flow in docs/deployment-checklist.md.',
            ],
            [
                'failure_event' => 'deployment.smoke.failed',
                'success_events' => ['deployment.smoke.completed'],
                'key' => 'deployment.smoke.failed',
                'message' => 'The most recent deployment smoke validation failed.',
                'remediation' => 'Fix the deployed-environment issue, then rerun bash scripts/deployment-smoke.sh against the target URL.',
            ],
        ];

        /** @var list<OpsAlert> $alerts */
        $alerts = [];

        foreach ($definitions as $definition) {
            $alert = $this->operationFailureAlert(
                $operations,
                $definition['failure_event'],
                $definition['success_events'],
                $definition['key'],
                $definition['message'],
                $definition['remediation']
            );

            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }

        return $alerts;
    }

    /**
     * @param list<OpsLogEntry> $operations
     * @param list<string> $successEvents
     * @return OpsAlert|null
     */
    private function operationFailureAlert(
        array $operations,
        string $failureEvent,
        array $successEvents,
        string $key,
        string $message,
        string $remediation
    ): ?array {
        $active = false;
        $detectedAt = '';
        $lastSeenAt = '';

        foreach (array_reverse($operations) as $entry) {
            $context = map_value($entry['context']);
            $event = string_value($context['event'] ?? '');
            $timestamp = string_value($entry['timestamp'] ?? '');

            if ($event === $failureEvent) {
                if (!$active) {
                    $detectedAt = $timestamp !== '' ? $timestamp : $this->currentTimestamp();
                }

                $active = true;
                $lastSeenAt = $timestamp !== '' ? $timestamp : $this->currentTimestamp();
                continue;
            }

            if (in_array($event, $successEvents, true)) {
                $active = false;
                $detectedAt = '';
                $lastSeenAt = '';
            }
        }

        if (!$active) {
            return null;
        }

        return $this->alert(
            $key,
            $message,
            $remediation,
            $detectedAt !== '' ? $detectedAt : $this->currentTimestamp(),
            $lastSeenAt !== '' ? $lastSeenAt : $this->currentTimestamp()
        );
    }

    /**
     * @param list<OpsLogEntry> $operations
     * @return array<string, array{active: bool, first_detected_at: string|null}>
     */
    private function alertNotificationCycles(array $operations): array
    {
        /** @var array<string, array{active: bool, first_detected_at: string|null}> $cycles */
        $cycles = [];

        foreach (array_reverse($operations) as $entry) {
            $context = map_value($entry['context']);
            $event = string_value($context['event'] ?? '');
            $key = string_value($context['alert_key'] ?? '');
            $timestamp = string_value($entry['timestamp'] ?? '');

            if ($key === '') {
                continue;
            }

            if ($event === 'ops.alert.sent') {
                $cycle = $cycles[$key] ?? ['active' => false, 'first_detected_at' => null];

                if (!$cycle['active']) {
                    $cycle['first_detected_at'] = $timestamp !== '' ? $timestamp : null;
                }

                $cycle['active'] = true;
                $cycles[$key] = $cycle;
                continue;
            }

            if ($event === 'ops.alert.resolved') {
                $cycles[$key] = [
                    'active' => false,
                    'first_detected_at' => null,
                ];
            }
        }

        return $cycles;
    }

    /**
     * @return OpsAlert
     */
    private function alert(
        string $key,
        string $message,
        string $remediation,
        string $detectedAt,
        string $lastSeenAt
    ): array {
        return [
            'key' => $key,
            'severity' => 'critical',
            'status' => 'active',
            'message' => $message,
            'remediation' => $remediation,
            'detected_at' => $detectedAt,
            'last_seen_at' => $lastSeenAt,
            'request_id' => $this->requestContext->requestId(),
        ];
    }

    private function alertEntityId(string $key): int
    {
        return (int) hexdec(substr(hash('sha256', $key), 0, 7));
    }

    private function currentTimestamp(): string
    {
        return date('c');
    }
}
