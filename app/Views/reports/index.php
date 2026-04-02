<?php
/** @var AppViewData $app */
/** @var ReportOverview $overview */
/** @var array<string, string> $filters */
/** @var string $dataset */
/** @var array<int, string> $departments */
/** @var array<int, string> $workflowStatuses */
/** @var array<int, string> $enrollmentStatuses */
/** @var array<int, string> $requestStatuses */
/** @var array<int, string> $notificationChannels */
/** @var array<int, string> $notificationStatuses */
/** @var \App\Core\ViewContext $view */
$appName = $app['name'];
$studentRows = $overview['studentRows'];
$requestRows = $overview['requestRows'];
$auditRows = $overview['auditRows'];
$notificationRows = $overview['notificationRows'];
$roleOverview = $overview['roleOverview'];
$deliverySummary = $overview['deliverySummary'];
$actionsHtml = $view->capture(static function () use ($dataset, $filters): void { ?>
    <a href="/reports/export/<?= e($dataset) ?>?<?= e(http_build_query($filters)) ?>" class="btn btn-primary">
        <i class="fas fa-file-csv"></i>
        <span>Export CSV</span>
    </a>
<?php });

$view->layout('layouts/base', [
    'title' => 'Reports | ' . $appName,
    'pageTitle' => 'Reporting and Exports',
    'pageDescription' => 'Review operational metrics, governance coverage, and exportable datasets.',
]);
?>
<?php $view->start('content'); ?>
<section class="module-panel module-panel--purple">
    <?= $view->renderPartial('partials/components/page_header', [
        'eyebrow' => 'Reporting',
        'title' => 'Operational reporting and exports',
        'description' => 'Export filtered operational data, review workload distribution, and inspect governance coverage from one screen.',
        'actionsHtml' => $actionsHtml,
    ]) ?>

    <div class="report-card-grid">
        <article class="report-stat-card">
            <span>Total students</span>
            <strong><?= e($overview['studentCount'] ?? 0) ?></strong>
        </article>
        <article class="report-stat-card">
            <span>Platform users</span>
            <strong><?= e($overview['userCount'] ?? 0) ?></strong>
        </article>
        <article class="report-stat-card">
            <span>Open request states</span>
            <strong><?= e(count($overview['requestStatusCounts'])) ?></strong>
        </article>
        <article class="report-stat-card">
            <span>Active roles</span>
            <strong><?= e(count($roleOverview)) ?></strong>
        </article>
        <article class="report-stat-card">
            <span>Overdue requests</span>
            <strong><?= e($overview['overdueRequestCount']) ?></strong>
        </article>
    </div>

    <form method="get" action="/reports" class="search-section row g-3">
        <div class="col-xl-2 col-md-4">
            <label class="form-label fw-semibold">Dataset</label>
            <select name="dataset" class="form-select">
                <option value="requests" <?= selected($dataset, 'requests') ?>>Requests</option>
                <option value="students" <?= selected($dataset, 'students') ?>>Students</option>
                <option value="audits" <?= selected($dataset, 'audits') ?>>Audit log</option>
                <option value="notifications" <?= selected($dataset, 'notifications') ?>>Notifications</option>
            </select>
        </div>
        <div class="col-xl-3 col-md-4">
            <label class="form-label fw-semibold">Search</label>
            <input name="search" class="form-control" value="<?= e($filters['search'] ?? '') ?>" placeholder="Search by student, title, or email">
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label fw-semibold">Workflow</label>
            <select name="status" class="form-select">
                <option value="">All workflow statuses</option>
                <?php foreach ($requestStatuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= selected($filters['status'] ?? '', $status) ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label fw-semibold">Enrollment</label>
            <select name="enrollment_status" class="form-select">
                <option value="">All enrollment statuses</option>
                <?php foreach ($enrollmentStatuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= selected($filters['enrollment_status'] ?? '', $status) ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label fw-semibold">Department</label>
            <select name="department" class="form-select">
                <option value="">All departments</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= e($department) ?>" <?= selected($filters['department'] ?? '', $department) ?>><?= e($department) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-xl-2 col-md-4">
            <label class="form-label fw-semibold">Channel</label>
            <select name="channel" class="form-select">
                <option value="">All channels</option>
                <?php foreach ($notificationChannels as $channel): ?>
                    <option value="<?= e($channel) ?>" <?= selected($filters['channel'] ?? '', $channel) ?>><?= e(strtoupper($channel)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-xl-1 col-md-4 d-grid">
            <label class="form-label fw-semibold opacity-0">Apply</label>
            <button class="btn btn-outline-primary">Apply</button>
        </div>
    </form>

    <div class="row row-cards g-4 mt-1">
        <div class="col-xl-8">
            <div class="table-shell">
                <?php if ($dataset === 'students'): ?>
                    <table class="table table-modern align-middle mb-0">
                        <thead><tr><th>Student</th><th>Program</th><th>Workflow</th><th>Enrollment</th></tr></thead>
                        <tbody>
                        <?php foreach ($studentRows as $student): ?>
                            <tr>
                                <td><div class="fw-semibold"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></div><div class="small text-muted"><?= e($student['student_number']) ?></div></td>
                                <td><?= e($student['program'] . ' • ' . $student['department']) ?></td>
                                <td><?= $view->renderPartial('partials/components/status_badge', ['label' => $student['latest_status']]) ?></td>
                                <td><?= $view->renderPartial('partials/components/status_badge', ['label' => $student['enrollment_status']]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php elseif ($dataset === 'audits'): ?>
                    <table class="table table-modern align-middle mb-0">
                        <thead><tr><th>Actor</th><th>Entity</th><th>Action</th><th>Timestamp</th></tr></thead>
                        <tbody>
                        <?php foreach ($auditRows as $entry): ?>
                            <tr>
                                <td><?= e($entry['actor_name'] ?? 'System') ?></td>
                                <td><?= e(ucfirst($entry['entity_type'])) ?> #<?= e($entry['entity_id']) ?></td>
                                <td><?= e(action_label($entry['action'])) ?></td>
                                <td><?= e($entry['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php elseif ($dataset === 'notifications'): ?>
                    <table class="table table-modern align-middle mb-0">
                        <thead><tr><th>Notification</th><th>User</th><th>Channel</th><th>Status</th><th>Recipient</th></tr></thead>
                        <tbody>
                        <?php foreach ($notificationRows as $notification): ?>
                            <tr>
                                <td><div class="fw-semibold"><?= e($notification['title']) ?></div><div class="small text-muted"><?= e($notification['created_at']) ?></div></td>
                                <td><?= e($notification['user_name']) ?></td>
                                <td><?= e(strtoupper($notification['channel'])) ?></td>
                                <td><?= $view->renderPartial('partials/components/status_badge', ['label' => $notification['status']]) ?></td>
                                <td><?= e($notification['recipient']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <table class="table table-modern align-middle mb-0">
                        <thead><tr><th>Request</th><th>Type</th><th>Status</th><th>Assigned</th></tr></thead>
                        <tbody>
                        <?php foreach ($requestRows as $request): ?>
                            <tr>
                                <td><div class="fw-semibold"><?= e($request['title']) ?></div><div class="small text-muted"><?= e($request['first_name'] . ' ' . $request['last_name']) ?> • <?= e($request['student_number']) ?></div></td>
                                <td><?= e($request['request_type']) ?></td>
                                <td><?= $view->renderPartial('partials/components/status_badge', ['label' => $request['status']]) ?></td>
                                <td><?= e($request['assigned_name'] ?? 'Unassigned') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card dashboard-card h-100">
                <div class="card-header">
                    <div>
                        <div class="section-pill">Access Model</div>
                        <h3 class="card-title mb-0 mt-2">Role coverage overview</h3>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($deliverySummary !== []): ?>
                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <?php foreach ($deliverySummary as $summary): ?>
                                <?= $view->renderPartial('partials/components/status_badge', [
                                    'label' => strtoupper($summary['channel']) . ' • ' . ($summary['status'] . ': ' . $summary['total']),
                                    'slug' => status_slug($summary['status']),
                                ]) ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="dashboard-activity-list">
                        <?php foreach ($roleOverview as $role): ?>
                            <article class="dashboard-activity-item">
                                <div class="dashboard-activity-item__icon"><i class="fas fa-shield-halved"></i></div>
                                <div class="dashboard-activity-item__body">
                                    <div class="dashboard-activity-item__title"><?= e($role['name']) ?></div>
                                    <div class="dashboard-activity-item__meta"><?= e($role['description']) ?></div>
                                    <div class="dashboard-activity-item__text"><?= e($role['user_count'] . ' assigned user(s)') ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php $view->end(); ?>
