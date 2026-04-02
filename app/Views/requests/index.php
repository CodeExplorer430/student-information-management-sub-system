<?php
/** @var AppViewData $app */
/** @var list<RequestRow> $requests */
/** @var array<string, string> $filters */
/** @var array<int, string> $requestTypes */
/** @var array<int, string> $requestStatuses */
/** @var array<int, string> $priorities */
/** @var array<int, string> $departments */
/** @var array<int, array<string, mixed>> $queueUsers */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$user = $app['user'];
$view->layout('layouts/base', [
    'title' => 'Request Center | ' . $appName,
    'pageTitle' => 'Request Center',
    'pageDescription' => 'Student self-service requests and registrar/staff operational queue.',
]);

$userRole = $user !== null ? $user['role'] : '';
$actionsHtml = in_array('requests.create', $app['permissions'], true)
    ? $view->capture(static function (): void { ?>
        <a href="/requests/create" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i>
            <span>Submit Request</span>
        </a>
    <?php })
    : '';

$bodyHtml = $view->capture(static function () use ($filters, $requestStatuses, $requestTypes, $priorities, $departments, $requests, $view): void { ?>
    <form class="card app-filter-card mt-4" method="get" action="/requests">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-4">
                    <label class="form-label fw-semibold">Search</label>
                    <input class="form-control" name="search" placeholder="Search by student, request, or title" value="<?= e($filters['search'] ?? '') ?>">
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($requestStatuses as $status): ?>
                            <option value="<?= e($status) ?>" <?= selected($filters['status'] ?? '', $status) ?>><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">Type</label>
                    <select class="form-select" name="request_type">
                        <option value="">All request types</option>
                        <?php foreach ($requestTypes as $type): ?>
                            <option value="<?= e($type) ?>" <?= selected($filters['request_type'] ?? '', $type) ?>><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">Priority</label>
                    <select class="form-select" name="priority">
                        <option value="">All priorities</option>
                        <?php foreach ($priorities as $priority): ?>
                            <option value="<?= e($priority) ?>" <?= selected($filters['priority'] ?? '', $priority) ?>><?= e($priority) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">Department</label>
                    <select class="form-select" name="department">
                        <option value="">All departments</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= e($department) ?>" <?= selected($filters['department'] ?? '', $department) ?>><?= e($department) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="overdue_only" name="overdue_only" <?= checked(($filters['overdue_only'] ?? '') === '1') ?>>
                        <label class="form-check-label" for="overdue_only">Overdue only</label>
                    </div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-outline-primary">
                        <i class="fas fa-filter"></i>
                        <span>Filter</span>
                    </button>
                    <a href="/requests" class="btn btn-outline-secondary">
                        <i class="fas fa-rotate-left"></i>
                        <span>Reset</span>
                    </a>
                </div>
            </div>
        </div>
    </form>

    <div class="card app-table-card mt-4">
        <div class="table-responsive app-table-shell">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Request</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Assigned</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($requests === []): ?>
                    <tr>
                        <td colspan="5">
                            <?= $view->renderPartial('partials/components/empty_state', [
                                'icon' => 'fa-list-check',
                                'title' => 'No requests found',
                                'description' => 'Try broadening the queue filters or clearing the overdue-only flag.',
                                'class' => 'app-empty-state--table',
                            ]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($request['title']) ?></div>
                                <div class="small text-muted">
                                    <?= e($request['first_name'] . ' ' . $request['last_name']) ?>
                                    • <?= e($request['student_number']) ?>
                                </div>
                            </td>
                            <td><?= e($request['request_type']) ?></td>
                            <td><?= $view->renderPartial('partials/components/status_badge', ['label' => $request['status']]) ?></td>
                            <td>
                                <div><?= e($request['assigned_name'] ?? 'Unassigned') ?></div>
                                <div class="small text-muted">
                                    <?= e($request['priority']) ?>
                                    <?php if ($request['due_at'] !== null && $request['due_at'] !== ''): ?>
                                        • Due <?= e(substr($request['due_at'], 0, 10)) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end"><a href="/requests/<?= e($request['id']) ?>" class="btn btn-sm btn-outline-primary">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php }); ?>
?>
<?php $view->start('content'); ?>
<?= $view->renderPartial('partials/components/page_section', [
    'tone' => 'blue',
    'eyebrow' => $userRole === 'student' ? 'Self Service' : 'Operational Queue',
    'title' => $userRole === 'student' ? 'My requests' : 'Request management queue',
    'description' => $userRole === 'student'
        ? 'Submit and track your requests without leaving the student workspace.'
        : 'Review, assign, and move requests through the operational workflow.',
    'actionsHtml' => $actionsHtml,
    'bodyHtml' => $bodyHtml,
]) ?>
<?php $view->end(); ?>
