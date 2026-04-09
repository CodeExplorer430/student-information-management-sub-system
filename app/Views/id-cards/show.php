<?php
/** @var AppViewData $app */
/** @var StudentRow $student */
/** @var IdCardRow $card */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'ID Card Preview | ' . $appName,
    'pageTitle' => 'Student ID Preview',
    'pageDescription' => $student['first_name'] . ' ' . $student['last_name'] . ' • ' . $student['student_number'],
    'pageBodyClass' => 'page-id-card-preview',
]);
?>
<?php $view->start('content'); ?>
<section class="module-panel module-panel--amber id-preview-page">
    <div class="module-panel__header">
        <div>
            <div class="section-pill">Student ID Preview</div>
            <h2>Student ID preview</h2>
            <p><?= e($student['first_name'] . ' ' . $student['last_name'] . ' • ' . $student['student_number']) ?></p>
        </div>
        <div class="hero-panel__actions">
            <a href="/id-cards/<?= e($student['id']) ?>/download" class="btn btn-primary"><i class="fas fa-download"></i><span>Download PNG</span></a>
            <a href="/id-cards/<?= e($student['id']) ?>/verify" class="btn btn-outline-primary"><i class="fas fa-shield-check"></i><span>Open verification</span></a>
            <button type="button" data-print-trigger class="btn btn-outline-primary"><i class="fas fa-print"></i><span>Print</span></button>
        </div>
    </div>

    <div class="id-preview-grid">
        <div class="id-preview-primary">
            <div class="generated-card-shell">
                <img src="data:image/png;base64,<?= e($card['image_data'] ?? '') ?>" alt="Generated student ID" class="img-fluid rounded shadow">
            </div>
            <div class="generated-card-meta">
                <span class="generated-card-chip">Generated at <?= e($card['generated_at'] ?? '') ?></span>
                <span class="generated-card-chip">Enrollment: <?= e($student['enrollment_status']) ?></span>
                <span class="generated-card-chip">Workflow: <?= e($student['latest_status']) ?></span>
            </div>
        </div>
        <aside class="id-preview-aside">
            <div class="detail-card__eyebrow">Issued card details</div>
            <h3>Record summary</h3>
            <div class="preview-chip-row">
                <span class="status-badge status-badge--<?= e(status_slug($student['enrollment_status'])) ?>"><?= e($student['enrollment_status']) ?></span>
                <span class="status-badge status-badge--<?= e(status_slug($student['latest_status'])) ?>"><?= e($student['latest_status']) ?></span>
            </div>
            <div class="id-preview-aside__brand">
                <img src="/assets/branding/bcp-logo.png" alt="Bestlink College of the Philippines logo">
                <div>
                    <strong>Official Bestlink student card</strong>
                    <span>Cleaned up for clearer print balance, easier barcode scans, and a more readable student identity block.</span>
                </div>
            </div>
            <dl class="detail-list detail-list--compact">
                <div><dt>Institution</dt><dd>Bestlink College of the Philippines</dd></div>
                <div><dt>Student</dt><dd><?= e($student['first_name'] . ' ' . $student['last_name']) ?></dd></div>
                <div><dt>Student Number</dt><dd><?= e($student['student_number']) ?></dd></div>
                <div><dt>Program</dt><dd><?= e($student['program']) ?></dd></div>
                <div><dt>Year / Department</dt><dd>Year <?= e($student['year_level']) ?> • <?= e($student['department']) ?></dd></div>
                <div><dt>Barcode</dt><dd><?= e($card['barcode_payload']) ?></dd></div>
                <div><dt>QR Verification</dt><dd>Ready for record checks.</dd></div>
            </dl>
        </aside>
    </div>
</section>
<?php $view->end(); ?>
