<?php
/** @var AppViewData $app */
/** @var list<AcademicRecordRow> $records */
/** @var array<string, string> $filters */
/** @var array<int, string> $departments */
/** @var array{page: int, per_page: int, total: int, page_count: int, from: int, to: int} $pagination */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Academic Records | ' . $appName,
    'pageTitle' => 'Academic Records Viewer',
    'pageDescription' => 'Access historical subjects and grades for authorized users.',
]);
$bodyHtml = $view->capture(static function () use ($filters, $departments, $records, $pagination, $view): void { ?>
    <?php
    $buildPageUrl = static function (int $page) use ($filters): string {
        $query = $filters;
        $query['page'] = (string) $page;
        $query = array_filter($query, static fn ($value): bool => $value !== '');

        return '/records?' . http_build_query($query);
    };
    ?>
    <form class="card app-filter-card mt-4" method="get" action="/records">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Student search</label>
                    <input class="form-control" name="student" value="<?= e($filters['student'] ?? '') ?>" placeholder="Search by student no. or name">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Department</label>
                    <select name="department" class="form-select">
                        <option value="">All departments</option>
                        <?php foreach (($departments ?? []) as $department): ?>
                            <option value="<?= e($department) ?>" <?= selected($filters['department'] ?? '', $department) ?>><?= e($department) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex flex-wrap align-items-end gap-2">
                    <button class="btn btn-primary"><i class="fas fa-search"></i><span>Search records</span></button>
                    <a href="/records" class="btn btn-outline-secondary"><i class="fas fa-rotate-left"></i><span>Reset</span></a>
                </div>
            </div>
        </div>
    </form>

    <div class="records-pagination-toolbar">
        <div>
            <strong><?= e((string) $pagination['total']) ?></strong> matched records
            <?php if ($pagination['total'] > 0): ?>
                <span class="text-muted">Showing <?= e((string) $pagination['from']) ?>-<?= e((string) $pagination['to']) ?> on page <?= e((string) $pagination['page']) ?>.</span>
            <?php endif; ?>
        </div>
        <?php if ($pagination['page_count'] > 1): ?>
            <nav class="records-pagination" aria-label="Academic records pages">
                <?php $previousPage = max(1, $pagination['page'] - 1); ?>
                <?php $nextPage = min($pagination['page_count'], $pagination['page'] + 1); ?>
                <a href="<?= e($buildPageUrl($previousPage)) ?>" class="btn btn-sm btn-outline-secondary<?= $pagination['page'] === 1 ? ' disabled' : '' ?>"<?= $pagination['page'] === 1 ? ' aria-disabled="true"' : '' ?>>Previous</a>
                <?php for ($page = 1; $page <= $pagination['page_count']; $page++): ?>
                    <a href="<?= e($buildPageUrl($page)) ?>" class="btn btn-sm <?= $page === $pagination['page'] ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= e((string) $page) ?></a>
                <?php endfor; ?>
                <a href="<?= e($buildPageUrl($nextPage)) ?>" class="btn btn-sm btn-outline-secondary<?= $pagination['page'] === $pagination['page_count'] ? ' disabled' : '' ?>"<?= $pagination['page'] === $pagination['page_count'] ? ' aria-disabled="true"' : '' ?>>Next</a>
            </nav>
        <?php endif; ?>
    </div>

    <div class="card app-table-card mt-4">
        <div class="table-responsive app-table-shell">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Student</th>
                    <th>Department</th>
                    <th>Term</th>
                    <th>Subject</th>
                    <th>Units</th>
                    <th>Grade</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($records === []): ?>
                    <tr>
                        <td colspan="6">
                            <?= $view->renderPartial('partials/components/empty_state', [
                                'icon' => 'fa-book-open-reader',
                                'title' => 'No academic records matched',
                                'description' => 'Try another student search or widen the department filter.',
                                'class' => 'app-empty-state--table',
                            ]) ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e(map_string($record, 'first_name') . ' ' . map_string($record, 'last_name')) ?></div>
                                <div class="small text-muted"><?= e(map_string($record, 'student_number')) ?></div>
                            </td>
                            <td><?= e(map_string($record, 'department')) ?></td>
                            <td><?= e($record['term_label']) ?></td>
                            <td>
                                <?= e($record['subject_code']) ?>
                                <div class="small text-muted"><?= e($record['subject_title']) ?></div>
                            </td>
                            <td><?= e($record['units']) ?></td>
                            <td><?= e($record['grade']) ?></td>
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
    'tone' => 'red',
    'eyebrow' => 'Academic Records Viewer',
    'title' => 'Academic records viewer',
    'description' => 'Search student academic history by student identity and department for registrar, faculty, and self-service review.',
    'features' => [
        ['icon' => 'fa-magnifying-glass-chart', 'title' => 'Historical lookup', 'description' => 'Search by student number, name, or email to get to the right transcript trail quickly.'],
        ['icon' => 'fa-graduation-cap', 'title' => 'Department filtering', 'description' => 'Restrict the result set when a review only concerns one academic unit.'],
        ['icon' => 'fa-user-shield', 'title' => 'Controlled visibility', 'description' => 'Record access remains role-bound and students only see their own academic history.'],
    ],
    'bodyHtml' => $bodyHtml,
]) ?>
<?php $view->end(); ?>
