<?php
/** @var AppViewData $app */
/** @var list<StudentRow> $students */
/** @var StudentFilters $filters */
/** @var array<int, string> $departments */
/** @var array<int, string> $workflowStatuses */
/** @var array<int, string> $enrollmentStatuses */
/** @var \App\Core\ViewContext $view */
$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Students | ' . $appName,
    'pageTitle' => 'Student Profiles',
    'pageDescription' => 'Search and filter student records across all submodules.',
]);
$permissions = $app['permissions'];
$actionsHtml = in_array('students.create', $permissions, true)
    ? $view->capture(static function (): void { ?>
        <a href="/students/create" class="btn btn-primary">
            <i class="fas fa-user-plus"></i>
            <span>Register student</span>
        </a>
    <?php })
    : '';

$bodyHtml = $view->capture(static function () use ($filters, $workflowStatuses, $enrollmentStatuses, $departments, $students, $view): void { ?>
    <form class="card app-filter-card mt-4" method="get" action="/students">
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
                    <button class="btn btn-primary">
                        <i class="fas fa-magnifying-glass"></i>
                        <span>Apply filters</span>
                    </button>
                    <a href="/students" class="btn btn-outline-secondary">
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
                    <th>Student</th>
                    <th>Program</th>
                    <th>Contact</th>
                    <th>Workflow</th>
                    <th>Enrollment</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($students === []): ?>
                    <tr>
                        <td colspan="6">
                            <?= $view->renderPartial('partials/components/empty_state', [
                                'icon' => 'fa-user-graduate',
                                'title' => 'No matching student profiles',
                                'description' => 'Try broadening the search or clearing one of the filters.',
                                'class' => 'app-empty-state--table',
                            ]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></div>
                                <div class="small text-muted"><?= e($student['student_number']) ?></div>
                            </td>
                            <td>
                                <?= e($student['program']) ?>
                                <div class="small text-muted"><?= e($student['department'] . ' • Year ' . $student['year_level']) ?></div>
                            </td>
                            <td>
                                <?= e($student['email']) ?>
                                <div class="small text-muted"><?= e($student['phone']) ?></div>
                            </td>
                            <td><?= $view->renderPartial('partials/components/status_badge', ['label' => $student['latest_status']]) ?></td>
                            <td><?= $view->renderPartial('partials/components/status_badge', ['label' => $student['enrollment_status']]) ?></td>
                            <td class="text-end"><a href="/students/<?= e($student['id']) ?>" class="btn btn-sm btn-outline-primary">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php }); ?>
<?php $view->start('content'); ?>
<?= $view->renderPartial('partials/components/page_section', [
    'tone' => 'blue',
    'eyebrow' => 'Registration Module',
    'title' => 'Student profile registration',
    'description' => 'Search and manage profile records, workflow progress, and enrollment standing from one place.',
    'actionsHtml' => $actionsHtml,
    'features' => [
        ['icon' => 'fa-id-badge', 'title' => 'Student number search', 'description' => 'Find profile records quickly using student number, name, or email filters.'],
        ['icon' => 'fa-shield-heart', 'title' => 'Enrollment visibility', 'description' => 'Keep request workflow and enrollment standing separate while exposing both in every result row.'],
        ['icon' => 'fa-people-group', 'title' => 'Department filters', 'description' => 'Slice the registry by department and registration dates for operational reviews.'],
    ],
    'bodyHtml' => $bodyHtml,
]) ?>
<?php $view->end(); ?>
