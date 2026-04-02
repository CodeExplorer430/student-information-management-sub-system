<?php
/** @var AppViewData $app */
/** @var list<StudentRow> $requests */
/** @var array<string, string> $filters */
/** @var array<int, string> $departments */
/** @var array<int, string> $workflowStatuses */
/** @var array<int, string> $enrollmentStatuses */
/** @var \App\Core\ViewContext $view */
$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Status Tracking | ' . $appName,
    'pageTitle' => 'Student Status Tracking',
    'pageDescription' => 'Track request progress, assigned personnel, and enrollment standing by student.',
]);

$requestRows = $requests;
$enrollmentCounts = [];
foreach ($requestRows as $row) {
    $status = $row['enrollment_status'];
    $enrollmentCounts[$status] = ($enrollmentCounts[$status] ?? 0) + 1;
}
$bodyHtml = $view->capture(static function () use ($filters, $workflowStatuses, $enrollmentStatuses, $departments, $requestRows, $enrollmentCounts, $view): void { ?>
    <form class="card app-filter-card mt-4" method="get" action="/statuses">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-4">
                    <label class="form-label fw-semibold">Search</label>
                    <input class="form-control" name="search" placeholder="Search by student no. or name" value="<?= e($filters['search'] ?? '') ?>">
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">Workflow</label>
                    <select name="status" class="form-select">
                        <option value="">All workflow statuses</option>
                        <?php foreach (($workflowStatuses ?? []) as $status): ?>
                            <option value="<?= e($status) ?>" <?= selected($filters['status'] ?? '', $status) ?>><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">Enrollment</label>
                    <select name="enrollment_status" class="form-select">
                        <option value="">All enrollment statuses</option>
                        <?php foreach (($enrollmentStatuses ?? []) as $status): ?>
                            <option value="<?= e($status) ?>" <?= selected($filters['enrollment_status'] ?? '', $status) ?>><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label fw-semibold">Department</label>
                    <select name="department" class="form-select">
                        <option value="">All departments</option>
                        <?php foreach (($departments ?? []) as $department): ?>
                            <option value="<?= e($department) ?>" <?= selected($filters['department'] ?? '', $department) ?>><?= e($department) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-1">
                    <label class="form-label fw-semibold">From</label>
                    <input type="date" class="form-control" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
                </div>
                <div class="col-lg-1">
                    <label class="form-label fw-semibold">To</label>
                    <input type="date" class="form-control" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
                </div>
                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary"><i class="fas fa-filter"></i><span>Filter board</span></button>
                    <a href="/statuses" class="btn btn-outline-secondary"><i class="fas fa-rotate-left"></i><span>Reset</span></a>
                </div>
            </div>
        </div>
    </form>

    <div class="row row-cols-2 row-cols-lg-4 g-3 mt-1">
        <?php foreach (($enrollmentStatuses ?? []) as $status): ?>
            <div class="col">
                <article class="status-summary-card status-summary-card--<?= e(status_slug($status)) ?>">
                    <div class="status-summary-card__label"><?= e($status) ?></div>
                    <div class="status-summary-card__value"><?= e($enrollmentCounts[$status] ?? 0) ?></div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card app-table-card mt-4">
        <div class="table-responsive app-table-shell">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Student</th><th>Department</th><th>Workflow</th><th>Enrollment</th><th>Updated</th><th></th></tr></thead>
                <tbody>
                <?php if ($requestRows === []): ?>
                    <tr>
                        <td colspan="6">
                            <?= $view->renderPartial('partials/components/empty_state', [
                                'icon' => 'fa-chart-line',
                                'title' => 'No workflow rows matched',
                                'description' => 'Try adjusting the date, department, or status filters.',
                                'class' => 'app-empty-state--table',
                            ]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requestRows as $request): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($request['first_name'] . ' ' . $request['last_name']) ?></div>
                                <div class="small text-muted"><?= e($request['student_number']) ?></div>
                            </td>
                            <td><?= e($request['department']) ?></td>
                            <td><?= $view->renderPartial('partials/components/status_badge', ['label' => $request['latest_status']]) ?></td>
                            <td><?= $view->renderPartial('partials/components/status_badge', ['label' => $request['enrollment_status']]) ?></td>
                            <td><?= e($request['latest_status_at'] ?? $request['created_at'] ?? '') ?></td>
                            <td class="text-end"><a href="/statuses/<?= e($request['id']) ?>" class="btn btn-sm btn-outline-primary">Timeline</a></td>
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
    'tone' => 'purple',
    'eyebrow' => 'Status Tracking Board',
    'title' => 'Status tracking board',
    'description' => 'Filter workflow requests and enrollment states without conflating operational processing with academic standing.',
    'bodyHtml' => $bodyHtml,
]) ?>
<?php $view->end(); ?>
