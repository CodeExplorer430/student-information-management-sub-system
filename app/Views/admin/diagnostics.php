<?php
/** @var AppViewData $app */
/** @var HealthReport $health */
/** @var array<string, array{status: string, message: string}> $deploymentReadiness */
/** @var array<string, array{status: string, message: string, path: string}> $directoryStatus */
/** @var array<string, array{status: string, message: string, path: string}> $assetStatus */
/** @var array{
 *     status: string,
 *     timestamp: string,
 *     request_id: string,
 *     counts: array{local: int, remote: int},
 *     thresholds: array{local_hours: int, remote_hours: int, drill_hours: int, remote_enabled: bool},
 *     latest: array{
 *         local_backup: array{backup_id: string|null, created_at: string|null, age_hours: float|null, location: string|null},
 *         verified_backup: array{backup_id: string|null, created_at: string|null, age_hours: float|null, location: string|null},
 *         export: array{backup_id: string|null, created_at: string|null, age_hours: float|null, location: string|null},
 *         remote_push: array{backup_id: string|null, created_at: string|null, age_hours: float|null, location: string|null},
 *         drill: array{backup_id: string|null, created_at: string|null, age_hours: float|null, location: string|null}
 *     },
 *     checks: list<array{name: string, status: string, message: string}>
 * } $backupStatus */
/** @var array{
 *     status: string,
 *     timestamp: string,
 *     request_id: string,
 *     counts: array{active: int, notified: int, resolved: int},
 *     active_alerts: list<array{
 *         key: string,
 *         severity: string,
 *         status: string,
 *         message: string,
 *         remediation: string,
 *         detected_at: string,
 *         last_seen_at: string,
 *         request_id: string
 *     }>,
 *     notified_keys: list<string>,
 *     resolved_keys: list<string>
 * } $opsAlerts */
/** @var list<LogEntry> $recentLogs */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Diagnostics | ' . $appName,
    'pageTitle' => 'Diagnostics',
    'pageDescription' => 'Review runtime readiness, deployment checks, and recent application events.',
]);
?>
<?php $view->start('content'); ?>
<div class="row g-4">
    <div class="col-xl-4">
        <section class="detail-card h-100">
            <div class="section-pill">Runtime</div>
            <h2 class="mt-3">Current readiness</h2>
            <p class="text-muted">Status: <strong class="text-uppercase"><?= e($health['status']) ?></strong></p>
            <div class="small text-muted mb-3">Request ID: <?= e($health['request_id']) ?></div>
            <div class="d-grid gap-2">
                <?php foreach ($health['checks'] as $check): ?>
                    <div class="border rounded-3 p-3">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-semibold"><?= e($check['name']) ?></div>
                                <div class="small text-muted"><?= e($check['message']) ?></div>
                            </div>
                            <span class="badge <?= $check['status'] === 'pass' ? 'bg-success-lt text-success' : 'bg-danger-lt text-danger' ?>">
                                <?= e($check['status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="col-xl-4">
        <section class="detail-card h-100">
            <div class="section-pill">Deployment</div>
            <h2 class="mt-3">Configuration checks</h2>
            <div class="d-grid gap-2 mt-3">
                <?php foreach ($deploymentReadiness as $name => $check): ?>
                    <div class="border rounded-3 p-3">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-semibold"><?= e($name) ?></div>
                                <div class="small text-muted"><?= e($check['message']) ?></div>
                            </div>
                            <span class="badge <?= $check['status'] === 'pass' ? 'bg-success-lt text-success' : 'bg-danger-lt text-danger' ?>">
                                <?= e($check['status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="col-xl-4">
        <section class="detail-card h-100">
            <div class="section-pill">Filesystem</div>
            <h2 class="mt-3">Runtime paths and assets</h2>
            <div class="d-grid gap-2 mt-3">
                <?php foreach ($directoryStatus as $name => $check): ?>
                    <div class="border rounded-3 p-3">
                        <div class="fw-semibold"><?= e($name) ?></div>
                        <div class="small text-muted"><?= e($check['message']) ?></div>
                        <div class="small text-muted"><?= e($check['path']) ?></div>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($assetStatus as $name => $check): ?>
                    <div class="border rounded-3 p-3">
                        <div class="fw-semibold"><?= e($name) ?></div>
                        <div class="small text-muted"><?= e($check['message']) ?></div>
                        <div class="small text-muted"><?= e($check['path']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="detail-card h-100">
            <div class="section-pill">Backups</div>
            <h2 class="mt-3">Backup health</h2>
            <p class="text-muted">Status: <strong class="text-uppercase"><?= e($backupStatus['status']) ?></strong></p>
            <div class="small text-muted mb-3">Local: <?= e((string) $backupStatus['counts']['local']) ?> | Remote: <?= e((string) $backupStatus['counts']['remote']) ?></div>
            <div class="d-grid gap-2 mb-4">
                <?php foreach ($backupStatus['checks'] as $check): ?>
                    <div class="border rounded-3 p-3">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div>
                                <div class="fw-semibold"><?= e($check['name']) ?></div>
                                <div class="small text-muted"><?= e($check['message']) ?></div>
                            </div>
                            <span class="badge <?= $check['status'] === 'pass' ? 'bg-success-lt text-success' : 'bg-danger-lt text-danger' ?>">
                                <?= e($check['status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="table-shell">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Checkpoint</th>
                        <th>Backup ID</th>
                        <th>Timestamp</th>
                        <th>Age (Hours)</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($backupStatus['latest'] as $name => $item): ?>
                        <tr>
                            <td><?= e($name) ?></td>
                            <td><?= e($item['backup_id'] ?? 'n/a') ?></td>
                            <td><?= e($item['created_at'] ?? 'n/a') ?></td>
                            <td><?= e($item['age_hours'] !== null ? number_format($item['age_hours'], 2) : 'n/a') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="detail-card h-100">
            <div class="section-pill">Operations</div>
            <h2 class="mt-3">Operational alerts</h2>
            <p class="text-muted">Status: <strong class="text-uppercase"><?= e($opsAlerts['status']) ?></strong></p>
            <div class="small text-muted mb-3">
                Active: <?= e((string) $opsAlerts['counts']['active']) ?> |
                Request ID: <?= e($opsAlerts['request_id']) ?>
            </div>
            <div class="d-grid gap-2">
                <?php if ($opsAlerts['active_alerts'] === []): ?>
                    <div class="border rounded-3 p-3 text-muted">No active operational alerts.</div>
                <?php else: ?>
                    <?php foreach ($opsAlerts['active_alerts'] as $alert): ?>
                        <div class="border rounded-3 p-3">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-semibold"><?= e($alert['key']) ?></div>
                                    <div class="small text-muted"><?= e($alert['message']) ?></div>
                                    <div class="small text-muted mt-2">Remediation: <?= e($alert['remediation']) ?></div>
                                    <div class="small text-muted mt-2">
                                        First detected: <?= e($alert['detected_at']) ?> |
                                        Last seen: <?= e($alert['last_seen_at']) ?>
                                    </div>
                                </div>
                                <span class="badge bg-danger-lt text-danger">
                                    <?= e($alert['severity']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="col-12">
        <section class="detail-card">
            <div class="section-pill">Logs</div>
            <h2 class="mt-3">Recent application events</h2>
            <p class="text-muted">Structured runtime log entries from <code>storage/logs/app.log</code>.</p>
            <div class="table-shell">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Time</th>
                        <th>Level</th>
                        <th>Channel</th>
                        <th>Request ID</th>
                        <th>Message</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($recentLogs === []): ?>
                        <tr>
                            <td colspan="5" class="text-muted">No application events recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $entry): ?>
                            <tr>
                                <td><?= e($entry['timestamp']) ?></td>
                                <td><?= e($entry['level']) ?></td>
                                <td><?= e($entry['channel']) ?></td>
                                <td><code><?= e($entry['request_id']) ?></code></td>
                                <td>
                                    <div class="fw-semibold"><?= e($entry['message']) ?></div>
                                    <?php if ($entry['context'] !== []): ?>
                                        <div class="small text-muted"><?= e(json_encode($entry['context'], JSON_THROW_ON_ERROR)) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
<?php $view->end(); ?>
