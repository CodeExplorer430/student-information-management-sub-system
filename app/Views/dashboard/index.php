<?php
/** @var AppViewData $app */
/** @var DashboardOverview $overview */
/** @var list<NotificationRow> $notifications */
/** @var \App\Core\ViewContext $view */

$currentUser = $app['user'];
$appName = $app['name'];
$role = $overview['role'] !== '' ? $overview['role'] : ($currentUser !== null ? $currentUser['role'] : 'guest');
$workflowCounts = $overview['workflowStatusCounts'];
$enrollmentCounts = $overview['enrollmentStatusCounts'];
$requestCounts = $overview['requestStatusCounts'];
$recentStudents = $overview['recentStudents'];
$recentActivity = $overview['recentActivity'];
$recentRequests = $overview['recentRequests'];
$roleOverview = $overview['roleOverview'];
$studentRecord = $overview['studentRecord'];
$permissions = $app['permissions'];
$overdueRequestCount = $overview['overdueRequestCount'];
$notificationUnreadCount = $overview['notificationUnreadCount'];
$profileCompleteness = $overview['profileCompleteness'];
$idAvailable = $overview['idAvailable'];
$deliverySummary = $overview['notificationDeliverySummary'];
$heroActionsHtml = $view->capture(static function () use ($permissions, $role, $studentRecord): void { ?>
    <?php if (in_array('students.create', $permissions, true)): ?>
        <a href="/students/create" class="btn btn-primary">
            <i class="fas fa-user-plus"></i>
            <span>Register Student</span>
        </a>
        <a href="/requests" class="btn btn-outline-primary">
            <i class="fas fa-list-check"></i>
            <span>Open Queue</span>
        </a>
        <?php if (in_array('reports.view', $permissions, true)): ?>
            <a href="/reports" class="btn btn-outline-secondary">
                <i class="fas fa-chart-pie"></i>
                <span>Reports</span>
            </a>
        <?php endif; ?>
    <?php elseif ($role === 'student'): ?>
        <a href="/requests/create" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i>
            <span>Submit Request</span>
        </a>
        <a href="/students/<?= e($studentRecord !== null ? $studentRecord['id'] : '') ?>" class="btn btn-outline-primary">
            <i class="fas fa-user"></i>
            <span>View Profile</span>
        </a>
    <?php else: ?>
        <a href="/records" class="btn btn-outline-primary">
            <i class="fas fa-book-open-reader"></i>
            <span>Open Records</span>
        </a>
        <?php if (in_array('reports.view', $permissions, true)): ?>
            <a href="/reports" class="btn btn-outline-secondary">
                <i class="fas fa-chart-pie"></i>
                <span>Reports</span>
            </a>
        <?php endif; ?>
    <?php endif; ?>
<?php });

$pageDescription = match ($role) {
    'admin' => 'Governance, users, permissions, and operational health.',
    'registrar', 'staff' => 'Operational queue, workflow actions, and enrollment decisions.',
    'student' => 'Your profile, requests, and lifecycle status at a glance.',
    'faculty' => 'Academic visibility and role-aware institutional updates.',
    default => 'Student lifecycle overview',
};

$view->layout('layouts/base', [
    'title' => 'Dashboard | ' . $appName,
    'pageTitle' => 'Dashboard',
    'pageDescription' => $pageDescription,
]);
?>
<?php $view->start('content'); ?>
<section class="dashboard-tabler-stack">
    <div class="hero-panel hero-panel--tabler">
        <?= $view->renderPartial('partials/components/page_header', [
            'eyebrow' => ucfirst($role) . ' workspace',
            'title' => match ($role) {
                'admin' => 'Governance and access oversight',
                'registrar', 'staff' => 'Operations control center',
                'student' => 'Self-service student dashboard',
                'faculty' => 'Academic visibility workspace',
                default => 'Student lifecycle overview',
            },
            'description' => match ($role) {
                'admin' => 'Review the user base, permission model, request backlog, and recent governance activity from one control surface.',
                'registrar', 'staff' => 'Manage incoming requests, watch workflow pressure, and move operational decisions through the queue.',
                'student' => 'Track your requests, current statuses, and record readiness without leaving the dashboard.',
                'faculty' => 'Review academic visibility and student lifecycle context relevant to your role.',
                default => 'Monitor registrations, status movement, and recent subsystem activity.',
            },
            'actionsHtml' => $heroActionsHtml,
            'class' => 'hero-panel__header',
        ]) ?>
    </div>

    <div class="row row-cards g-3 dashboard-summary-grid">
        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-summary-card dashboard-summary-card--total">
                <div class="card-body">
                    <div class="dashboard-summary-card__meta">Students</div>
                    <div class="dashboard-summary-card__value"><?= e($overview['studentCount']) ?></div>
                    <div class="dashboard-summary-card__foot">Profiles in the active system</div>
                </div>
            </div>
        </div>
        <?php if ($role === 'admin'): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="card dashboard-summary-card dashboard-summary-card--workflow">
                    <div class="card-body">
                        <div class="dashboard-summary-card__meta">Users</div>
                        <div class="dashboard-summary-card__value"><?= e($overview['userCount']) ?></div>
                        <div class="dashboard-summary-card__foot">Active platform accounts</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php foreach ($requestCounts as $label => $total): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="card dashboard-summary-card dashboard-summary-card--workflow">
                    <div class="card-body">
                        <div class="dashboard-summary-card__meta">Requests</div>
                        <div class="dashboard-summary-card__value"><?= e($total) ?></div>
                        <div class="dashboard-summary-card__foot"><?= e((string) $label) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-summary-card dashboard-summary-card--workflow">
                <div class="card-body">
                    <div class="dashboard-summary-card__meta">Overdue requests</div>
                    <div class="dashboard-summary-card__value"><?= e($overdueRequestCount) ?></div>
                    <div class="dashboard-summary-card__foot">Items past due target</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-summary-card dashboard-summary-card--workflow">
                <div class="card-body">
                    <div class="dashboard-summary-card__meta">Unread notifications</div>
                    <div class="dashboard-summary-card__value"><?= e($notificationUnreadCount) ?></div>
                    <div class="dashboard-summary-card__foot">In-app updates waiting</div>
                </div>
            </div>
        </div>
        <?php foreach ($enrollmentCounts as $label => $total): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="card dashboard-summary-card dashboard-summary-card--enrollment">
                    <div class="card-body">
                        <div class="dashboard-summary-card__meta">Enrollment</div>
                        <div class="dashboard-summary-card__value"><?= e($total) ?></div>
                        <div class="dashboard-summary-card__foot"><?= e((string) $label) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($role === 'student' && $studentRecord !== null): ?>
        <div class="row row-cards g-4">
            <div class="col-xl-6">
                <div class="card dashboard-card h-100">
                    <div class="card-header">
                        <div>
                            <div class="section-pill">Profile Readiness</div>
                            <h3 class="card-title mb-0 mt-2">Self-service readiness</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="display-6 fw-bold"><?= e((int) $profileCompleteness) ?>%</div>
                        <p class="text-muted">Core student profile fields completed for registrar and request workflows.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <?= $view->renderPartial('partials/components/status_badge', [
                                'label' => $idAvailable ? 'ID available' : 'ID pending',
                                'slug' => $idAvailable ? 'active' : 'pending',
                            ]) ?>
                            <?= $view->renderPartial('partials/components/status_badge', [
                                'label' => $studentRecord['enrollment_status'],
                            ]) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card dashboard-card h-100">
                    <div class="card-header">
                        <div>
                            <div class="section-pill">Communications</div>
                            <h3 class="card-title mb-0 mt-2">Recent notifications</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="dashboard-activity-list">
                            <?php if ($notifications === []): ?>
                                <p class="text-muted mb-0">No notifications yet.</p>
                            <?php else: ?>
                                <?php foreach (array_slice($notifications, 0, 4) as $notification): ?>
                                    <article class="dashboard-activity-item">
                                        <div class="dashboard-activity-item__icon"><i class="fas fa-bell"></i></div>
                                        <div class="dashboard-activity-item__body">
                                            <div class="dashboard-activity-item__title"><?= e($notification['title']) ?></div>
                                            <div class="dashboard-activity-item__meta"><?= e($notification['created_at']) ?></div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row row-cards g-4">
        <div class="col-xl-7">
            <div class="card dashboard-card h-100">
                <div class="card-header">
                    <div>
                        <div class="section-pill"><?= e($role === 'student' ? 'My Requests' : 'Operations') ?></div>
                        <h3 class="card-title mb-0 mt-2"><?= e($role === 'student' ? 'Recent requests' : 'Recent operational requests') ?></h3>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table table-modern mb-0">
                        <thead>
                        <tr>
                            <th><?= e($role === 'student' ? 'Request' : 'Student / Request') ?></th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($recentRequests === []): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No requests found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentRequests as $request): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($request['title']) ?></div>
                                        <div class="small text-muted">
                                            <?php if ($role !== 'student'): ?>
                                                <?= e($request['first_name'] . ' ' . $request['last_name']) ?> •
                                            <?php endif; ?>
                                            <?= e($request['student_number']) ?>
                                        </div>
                                    </td>
                                    <td><?= e($request['request_type']) ?></td>
                                    <td><?= $view->renderPartial('partials/components/status_badge', ['label' => $request['status']]) ?></td>
                                    <td class="text-end"><a href="/requests/<?= e($request['id']) ?>" class="btn btn-sm btn-outline-primary">Open</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card dashboard-card h-100">
                <div class="card-header">
                    <div>
                        <div class="section-pill"><?= e($role === 'admin' ? 'Access Model' : 'Governance') ?></div>
                        <h3 class="card-title mb-0 mt-2"><?= e($role === 'admin' ? 'Role coverage overview' : 'Recent audit activity') ?></h3>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($role === 'admin'): ?>
                        <div class="dashboard-activity-list">
                            <?php foreach ($roleOverview as $roleEntry): ?>
                                <article class="dashboard-activity-item">
                                    <div class="dashboard-activity-item__icon"><i class="fas fa-user-shield"></i></div>
                                    <div class="dashboard-activity-item__body">
                                        <div class="dashboard-activity-item__title"><?= e($roleEntry['name']) ?></div>
                                        <div class="dashboard-activity-item__meta"><?= e($roleEntry['description']) ?></div>
                                        <div class="dashboard-activity-item__text"><?= e($roleEntry['user_count'] . ' assigned user(s)') ?></div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="dashboard-activity-list">
                            <?php if ($recentActivity === []): ?>
                                <p class="text-muted mb-0">No audit activity logged yet.</p>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $entry): ?>
                                    <article class="dashboard-activity-item">
                                        <div class="dashboard-activity-item__icon"><i class="fas fa-shield-heart"></i></div>
                                        <div class="dashboard-activity-item__body">
                                            <div class="dashboard-activity-item__title"><?= e(action_label($entry['action'])) ?></div>
                                            <div class="dashboard-activity-item__meta"><?= e(($entry['actor_name'] ?? 'System') . ' • ' . $entry['created_at']) ?></div>
                                            <div class="dashboard-activity-item__text"><?= e(ucfirst($entry['entity_type'])) ?> #<?= e($entry['entity_id']) ?></div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($role === 'admin' && $deliverySummary !== []): ?>
                        <div class="mt-4 d-flex flex-wrap gap-2">
                            <?php foreach ($deliverySummary as $summary): ?>
                                <?= $view->renderPartial('partials/components/status_badge', [
                                    'label' => strtoupper($summary['channel']) . ' • ' . ($summary['status'] . ': ' . $summary['total']),
                                    'slug' => status_slug($summary['status']),
                                ]) ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (in_array($role, ['admin', 'registrar', 'staff'], true)): ?>
        <div class="row row-cards g-4">
            <div class="col-xl-12">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <div>
                            <div class="section-pill">Recent Profiles</div>
                            <h3 class="card-title mb-0 mt-2">Recently registered students</h3>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table table-modern mb-0">
                            <thead>
                            <tr>
                                <th>Student</th>
                                <th>Program</th>
                                <th>Department</th>
                                <th class="text-end">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($recentStudents === []): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No students found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentStudents as $student): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></div>
                                            <div class="small text-muted"><?= e($student['student_number']) ?></div>
                                        </td>
                                        <td><?= e($student['program']) ?></td>
                                        <td><?= e($student['department']) ?></td>
                                        <td class="text-end"><a href="/students/<?= e($student['id']) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php $view->end(); ?>
