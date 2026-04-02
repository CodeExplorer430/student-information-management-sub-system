<?php
/** @var AppViewData $app */
/** @var StudentRow $student */
/** @var IdCardRow $card */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'ID Verification | ' . $appName,
]);
?>
<?php $view->start('content'); ?>
<section class="verification-panel">
    <div class="section-pill">Student ID verification</div>
    <h1>Verified Bestlink College student record</h1>
    <p>This verification page is intended for attendance and record checks triggered from the student ID QR code.</p>

    <dl class="detail-list detail-list--spacious">
        <div><dt>Student Number</dt><dd><?= e($student['student_number']) ?></dd></div>
        <div><dt>Name</dt><dd><?= e($student['first_name'] . ' ' . $student['last_name']) ?></dd></div>
        <div><dt>Program</dt><dd><?= e($student['program']) ?></dd></div>
        <div><dt>Year Level</dt><dd><?= e($student['year_level']) ?></dd></div>
        <div><dt>Enrollment</dt><dd><span class="status-badge status-badge--<?= e(status_slug($student['enrollment_status'])) ?>"><?= e($student['enrollment_status']) ?></span></dd></div>
        <div><dt>Workflow</dt><dd><span class="status-badge status-badge--<?= e(status_slug($student['latest_status'])) ?>"><?= e($student['latest_status']) ?></span></dd></div>
        <div><dt>Card Generated</dt><dd><?= e($card['generated_at']) ?></dd></div>
    </dl>
</section>
<?php $view->end(); ?>
