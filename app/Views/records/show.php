<?php
/** @var AppViewData $app */
/** @var StudentRow $student */
/** @var list<AcademicRecordRow> $records */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Student Records | ' . $appName,
    'pageTitle' => 'Academic Record History',
    'pageDescription' => $student['first_name'] . ' ' . $student['last_name'] . ' • ' . $student['student_number'],
]);
?>
<?php $view->start('content'); ?>
<section class="module-panel module-panel--red">
    <div class="module-panel__header">
        <div>
            <div class="section-pill">Student Transcript</div>
            <h2>Academic record history</h2>
            <p><?= e($student['first_name'] . ' ' . $student['last_name'] . ' • ' . $student['student_number']) ?></p>
        </div>
        <div class="hero-panel__actions">
            <a href="/records/<?= e($student['id']) ?>/export" class="btn btn-primary">
                <i class="fas fa-file-arrow-down"></i>
                <span>Export CSV</span>
            </a>
            <a href="/students/<?= e($student['id']) ?>" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i>
                <span>Back to profile</span>
            </a>
        </div>
    </div>

    <div class="table-shell">
        <table class="table table-modern align-middle mb-0">
            <thead><tr><th>Term</th><th>Subject Code</th><th>Subject</th><th>Units</th><th>Grade</th></tr></thead>
            <tbody>
            <?php if ($records === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No academic records available.</td></tr>
            <?php else: ?>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= e($record['term_label']) ?></td>
                        <td><?= e($record['subject_code']) ?></td>
                        <td><?= e($record['subject_title']) ?></td>
                        <td><?= e($record['units']) ?></td>
                        <td><?= e($record['grade']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php $view->end(); ?>
