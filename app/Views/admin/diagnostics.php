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

$deploymentLabels = [
    'app_env' => 'Application environment',
    'app_debug' => 'Debug mode',
    'app_key' => 'Application key',
    'app_url' => 'Public app URL',
    'session_secure' => 'Secure session cookies',
    'db_driver' => 'Database runtime',
    'default_password' => 'Default password rotation',
    'email_driver' => 'Email delivery',
    'sms_driver' => 'SMS delivery',
];
$pathLabels = [
    'session_path' => 'Session storage',
    'private_uploads' => 'Private uploads',
    'public_id_cards' => 'Generated ID storage',
    'bootstrap_css' => 'Bootstrap CSS',
    'bootstrap_js' => 'Bootstrap JS',
    'tabler_css' => 'Tabler CSS',
    'tabler_js' => 'Tabler JS',
];
$checkLabel = static function (string $name, array $labels): string {
    return string_value($labels[$name] ?? ucwords(str_replace('_', ' ', $name)));
};
$attentionItems = [];
foreach ($health['checks'] as $check) {
    if ($check['status'] !== 'pass') {
        $attentionItems[] = [
            'area' => 'Runtime',
            'title' => $checkLabel((string) $check['name'], []),
            'detail' => (string) $check['message'],
            'technical' => (string) $check['name'],
        ];
    }
}
foreach ($deploymentReadiness as $name => $check) {
    if ($check['status'] !== 'pass') {
        $attentionItems[] = [
            'area' => 'Deployment',
            'title' => $checkLabel((string) $name, $deploymentLabels),
            'detail' => (string) $check['message'],
            'technical' => (string) $name,
        ];
    }
}
foreach ($backupStatus['checks'] as $check) {
    if (($check['status'] ?? '') !== 'pass') {
        $attentionItems[] = [
            'area' => 'Backups',
            'title' => $checkLabel((string) $check['name'], []),
            'detail' => (string) $check['message'],
            'technical' => (string) $check['name'],
        ];
    }
}
foreach ($opsAlerts['active_alerts'] as $alert) {
    $attentionItems[] = [
        'area' => 'Operations',
        'title' => (string) $alert['key'],
        'detail' => (string) $alert['message'],
        'technical' => (string) $alert['severity'],
    ];
}
$attentionCount = count($attentionItems);
$attentionItems = $attentionItems ?: [['area' => 'Healthy', 'title' => 'No immediate admin action required', 'detail' => 'Runtime, deployment, backup, and operations signals are currently stable.', 'technical' => 'all_checks_passing']];
$runtimePassCount = count(array_filter($health['checks'], static fn (array $check): bool => $check['status'] === 'pass'));
$deploymentPassCount = count(array_filter($deploymentReadiness, static fn (array $check): bool => $check['status'] === 'pass'));
$diagnosticTabs = [
    ['id' => 'overview', 'label' => 'Overview'],
    ['id' => 'attention', 'label' => 'Attention', 'count' => $attentionCount],
    ['id' => 'runtime', 'label' => 'Runtime'],
    ['id' => 'deployment', 'label' => 'Deployment'],
    ['id' => 'filesystem', 'label' => 'Filesystem'],
    ['id' => 'backups', 'label' => 'Backups'],
    ['id' => 'operations', 'label' => 'Operations'],
    ['id' => 'logs', 'label' => 'Logs'],
];
?>
<?php $view->start('content'); ?>
<section class="admin-workspace admin-workspace--diagnostics">
    <div class="workspace-tab-list workspace-tab-list--wide" role="tablist" aria-label="Diagnostics tabs" data-tab-list>
        <?php foreach ($diagnosticTabs as $index => $tab): ?>
            <button type="button"
                    class="workspace-tab<?= $index === 0 ? ' is-active' : '' ?>"
                    id="diagnostics-tab-<?= e($tab['id']) ?>"
                    role="tab"
                    aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                    aria-controls="diagnostics-panel-<?= e($tab['id']) ?>"
                    data-tab-trigger
                    data-tab-target="diagnostics-panel-<?= e($tab['id']) ?>">
                <span><?= e($tab['label']) ?></span>
                <?php if (isset($tab['count']) && $tab['count'] > 0): ?>
                    <span class="workspace-tab__count"><?= e((string) $tab['count']) ?></span>
                <?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>

    <section class="diagnostic-hero" id="diagnostics-panel-overview" role="tabpanel" aria-labelledby="diagnostics-tab-overview" data-tab-panel>
        <div>
            <div class="section-pill">Overview</div>
            <h2>Admin health overview</h2>
            <p>See what is healthy, what needs action, and where technical details live before you troubleshoot deeper.</p>
            <div class="small text-muted">Request ID: <?= e($health['request_id']) ?></div>
        </div>
        <div class="diagnostic-summary-grid">
            <div class="diagnostic-summary-metric">
                <span>Current readiness</span>
                <strong class="text-uppercase"><?= e($health['status']) ?></strong>
                <small><?= e((string) $runtimePassCount) ?> of <?= e((string) count($health['checks'])) ?> runtime checks passed</small>
            </div>
            <div class="diagnostic-summary-metric">
                <span>Deployment checks</span>
                <strong><?= e((string) $deploymentPassCount) ?>/<?= e((string) count($deploymentReadiness)) ?></strong>
                <small>Production-readiness checkpoints currently passing</small>
            </div>
            <div class="diagnostic-summary-metric">
                <span>Active issues</span>
                <strong><?= e((string) $attentionCount) ?></strong>
                <small>Items needing review across runtime, backups, and operations</small>
            </div>
        </div>
    </section>

    <section class="diagnostic-panel" id="diagnostics-panel-attention" role="tabpanel" aria-labelledby="diagnostics-tab-attention" data-tab-panel hidden>
        <div class="section-pill">Attention</div>
        <h2>Items that need action</h2>
        <div class="diagnostic-attention-list">
            <?php foreach ($attentionItems as $item): ?>
                <article class="diagnostic-attention-item<?= $item['area'] === 'Healthy' ? ' diagnostic-attention-item--quiet' : '' ?>">
                    <div class="diagnostic-attention-item__meta"><?= e($item['area']) ?></div>
                    <h3><?= e($item['title']) ?></h3>
                    <p><?= e($item['detail']) ?></p>
                    <code><?= e($item['technical']) ?></code>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="diagnostic-panel" id="diagnostics-panel-runtime" role="tabpanel" aria-labelledby="diagnostics-tab-runtime" data-tab-panel hidden>
        <div class="section-pill">Runtime</div>
        <h2>Current readiness</h2>
        <div class="diagnostic-check-list">
            <?php foreach ($health['checks'] as $check): ?>
                <div class="diagnostic-check-row">
                    <div>
                        <strong><?= e($checkLabel((string) $check['name'], [])) ?></strong>
                        <p><?= e($check['message']) ?></p>
                        <code><?= e((string) $check['name']) ?></code>
                    </div>
                    <span class="badge <?= $check['status'] === 'pass' ? 'bg-success-lt text-success' : 'bg-danger-lt text-danger' ?>">
                        <?= e($check['status']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="diagnostic-panel" id="diagnostics-panel-deployment" role="tabpanel" aria-labelledby="diagnostics-tab-deployment" data-tab-panel hidden>
        <div class="section-pill">Deployment</div>
        <h2>Configuration checks</h2>
        <div class="diagnostic-check-list">
            <?php foreach ($deploymentReadiness as $name => $check): ?>
                <div class="diagnostic-check-row">
                    <div>
                        <strong><?= e($checkLabel((string) $name, $deploymentLabels)) ?></strong>
                        <p><?= e($check['message']) ?></p>
                        <code><?= e((string) $name) ?></code>
                    </div>
                    <span class="badge <?= $check['status'] === 'pass' ? 'bg-success-lt text-success' : 'bg-danger-lt text-danger' ?>">
                        <?= e($check['status']) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="diagnostic-panel" id="diagnostics-panel-filesystem" role="tabpanel" aria-labelledby="diagnostics-tab-filesystem" data-tab-panel hidden>
        <div class="section-pill">Filesystem</div>
        <h2>Runtime paths and assets</h2>
        <div class="diagnostic-resource-list">
            <?php foreach ($directoryStatus as $name => $check): ?>
                <div class="diagnostic-resource-row">
                    <strong><?= e($checkLabel((string) $name, $pathLabels)) ?></strong>
                    <p><?= e($check['message']) ?></p>
                    <code><?= e($check['path']) ?></code>
                </div>
            <?php endforeach; ?>
            <?php foreach ($assetStatus as $name => $check): ?>
                <div class="diagnostic-resource-row">
                    <strong><?= e($checkLabel((string) $name, $pathLabels)) ?></strong>
                    <p><?= e($check['message']) ?></p>
                    <code><?= e($check['path']) ?></code>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="diagnostic-panel" id="diagnostics-panel-backups" role="tabpanel" aria-labelledby="diagnostics-tab-backups" data-tab-panel hidden>
        <div class="section-pill">Backups</div>
        <h2>Backup health</h2>
        <p class="text-muted">Local backups: <?= e((string) $backupStatus['counts']['local']) ?> | Remote exports: <?= e((string) $backupStatus['counts']['remote']) ?></p>
        <div class="diagnostic-check-list mb-4">
            <?php foreach ($backupStatus['checks'] as $check): ?>
                <div class="diagnostic-check-row">
                    <div>
                        <strong><?= e($checkLabel((string) $check['name'], [])) ?></strong>
                        <p><?= e($check['message']) ?></p>
                    </div>
                    <span class="badge <?= $check['status'] === 'pass' ? 'bg-success-lt text-success' : 'bg-danger-lt text-danger' ?>">
                        <?= e($check['status']) ?>
                    </span>
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
                        <td><?= e($checkLabel((string) $name, [])) ?></td>
                        <td><?= e($item['backup_id'] ?? 'n/a') ?></td>
                        <td><?= e($item['created_at'] ?? 'n/a') ?></td>
                        <td><?= e($item['age_hours'] !== null ? number_format($item['age_hours'], 2) : 'n/a') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="diagnostic-panel" id="diagnostics-panel-operations" role="tabpanel" aria-labelledby="diagnostics-tab-operations" data-tab-panel hidden>
        <div class="section-pill">Operations</div>
        <h2>Operational alerts</h2>
        <p class="text-muted">Active alerts: <?= e((string) $opsAlerts['counts']['active']) ?> | Request ID: <?= e($opsAlerts['request_id']) ?></p>
        <div class="diagnostic-attention-list">
            <?php if ($opsAlerts['active_alerts'] === []): ?>
                <article class="diagnostic-attention-item diagnostic-attention-item--quiet">
                    <h3>No active operational alerts.</h3>
                    <p>The system has not raised any unresolved operator-visible incidents.</p>
                </article>
            <?php else: ?>
                <?php foreach ($opsAlerts['active_alerts'] as $alert): ?>
                    <article class="diagnostic-attention-item">
                        <div class="diagnostic-attention-item__meta"><?= e($alert['severity']) ?></div>
                        <h3><?= e($alert['key']) ?></h3>
                        <p><?= e($alert['message']) ?></p>
                        <div class="small text-muted">Remediation: <?= e($alert['remediation']) ?></div>
                        <div class="small text-muted">First detected: <?= e($alert['detected_at']) ?> | Last seen: <?= e($alert['last_seen_at']) ?></div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="diagnostic-panel" id="diagnostics-panel-logs" role="tabpanel" aria-labelledby="diagnostics-tab-logs" data-tab-panel hidden>
        <div class="section-pill">Logs</div>
        <h2>Recent application events</h2>
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
</section>
<?php $view->end(); ?>
