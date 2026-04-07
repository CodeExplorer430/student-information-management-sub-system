<?php
/** @var AppViewData $app */
/** @var string $csrf */
/** @var list<StudentRow> $students */
/** @var array<string, string> $filters */
/** @var array<int, string> $departments */
/** @var array<int, string> $workflowStatuses */
/** @var array<int, string> $enrollmentStatuses */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$permissions = $app['permissions'];
$view->layout('layouts/base', [
    'title' => 'ID Generation | ' . $appName,
    'pageTitle' => 'Student ID Generation',
    'pageDescription' => 'Generate the official student ID with QR code, barcode, and print/download actions.',
]);
$bodyHtml = $view->capture(static function () use ($filters, $workflowStatuses, $enrollmentStatuses, $departments, $permissions, $csrf, $students, $view): void { ?>
    <form class="card app-filter-card mt-4" method="get" action="/id-cards">
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
                <div class="col-lg-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100"><i class="fas fa-filter"></i><span>Filter list</span></button>
                </div>
            </div>
        </div>
    </form>

    <?php if (in_array('id_cards.generate', $permissions, true)): ?>
        <form method="post" action="/id-cards/generate" class="card app-form-card mt-4">
            <div class="card-body">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="_back" value="/id-cards">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-9">
                        <label for="student_id" class="form-label fw-semibold">Student</label>
                        <select id="student_id" name="student_id" class="form-select" required>
                            <option value="">Select a student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= e($student['id']) ?>"><?= e($student['student_number'] . ' • ' . $student['first_name'] . ' ' . $student['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3 d-grid">
                        <button class="btn btn-primary">
                            <i class="fas fa-id-card"></i>
                            <span>Generate now</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <div class="card app-table-card mt-4">
        <div class="table-responsive app-table-shell">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Student</th><th>Workflow</th><th>Enrollment</th><th>ID Card</th><th></th></tr></thead>
                <tbody>
                <?php if ($students === []): ?>
                    <tr>
                        <td colspan="5">
                            <?= $view->renderPartial('partials/components/empty_state', [
                                'icon' => 'fa-id-card',
                                'title' => 'No student records available',
                                'description' => 'Adjust the search filters or register a student before generating IDs.',
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
                            <td><?= $view->renderPartial('partials/components/status_badge', ['label' => $student['latest_status']]) ?></td>
                            <td><?= $view->renderPartial('partials/components/status_badge', ['label' => $student['enrollment_status']]) ?></td>
                            <td>
                                <?php if (!empty($student['id_card_path'])): ?>
                                    <?= $view->renderPartial('partials/components/status_badge', ['label' => 'Generated', 'slug' => 'generated']) ?>
                                <?php else: ?>
                                    <?= $view->renderPartial('partials/components/status_badge', ['label' => 'Pending issuance', 'slug' => 'queued']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (!empty($student['id_card_path'])): ?>
                                    <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                        <a href="/id-cards/<?= e($student['id']) ?>/print" class="btn btn-sm btn-outline-primary">Preview</a>
                                        <a href="/id-cards/<?= e($student['id']) ?>/download" class="btn btn-sm btn-primary">Download</a>
                                        <a href="/id-cards/<?= e($student['id']) ?>/verify" class="btn btn-sm btn-outline-secondary">Verify</a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">Generate above</span>
                                <?php endif; ?>
                            </td>
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
    'tone' => 'amber',
    'eyebrow' => 'Student ID Generation',
    'title' => 'Student ID generation',
    'description' => 'Search students, filter eligibility context, and generate a printable school ID with QR verification.',
    'features' => [
        ['icon' => 'fa-qrcode', 'title' => 'QR verification', 'description' => 'Each generated card resolves to a verification page for quick attendance or identity checks.'],
        ['icon' => 'fa-barcode', 'title' => 'Download and print', 'description' => 'Preview the card first, then print or download a PNG artifact for distribution.'],
        ['icon' => 'fa-filter-circle-dollar', 'title' => 'Operational filtering', 'description' => 'Limit the generation list by workflow, enrollment status, and department before issuing cards.'],
    ],
    'bodyHtml' => $bodyHtml,
]) ?>
<?php $view->end(); ?>
