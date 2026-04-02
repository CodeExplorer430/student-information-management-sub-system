<?php
/** @var AppViewData $app */
/** @var string $csrf */
/** @var array<string, mixed>|null $student */
/** @var array<string, array<int, string>> $errors */
/** @var \App\Core\ViewContext $view */
$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Register Student | ' . $appName,
    'pageTitle' => 'Student Profile Registration',
    'pageDescription' => 'Capture student demographics, guardian information, and photo upload.',
]);
?>
<?php $view->start('content'); ?>
<section class="module-panel module-panel--blue">
    <div class="module-panel__header">
        <div>
            <div class="section-pill">Student Profile Registration</div>
            <h2>Create a new student record</h2>
            <p>Build the primary student profile with contact details, program data, guardian information, and photo upload.</p>
        </div>
    </div>

    <div class="feature-grid">
        <article class="feature-item">
            <div class="feature-icon"><i class="fas fa-file-shield"></i></div>
            <div class="feature-text"><h4>Validated inputs</h4><p>Required fields are checked before a profile is created and student numbers are generated centrally.</p></div>
        </article>
        <article class="feature-item">
            <div class="feature-icon"><i class="fas fa-camera"></i></div>
            <div class="feature-text"><h4>Photo capture</h4><p>Upload a profile image now so the student ID module can generate a complete card later.</p></div>
        </article>
        <article class="feature-item">
            <div class="feature-icon"><i class="fas fa-clock-rotate-left"></i></div>
            <div class="feature-text"><h4>Audit-ready data</h4><p>Registration events feed the profile, status, and audit views without duplicated form entry.</p></div>
        </article>
    </div>

    <form method="post" action="/students" enctype="multipart/form-data" class="data-form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="_back" value="/students/create">
        <?= $view->renderPartial('students/_form', ['student' => $student ?? [], 'errors' => $errors ?? []]) ?>
        <div class="form-actions">
            <button class="btn btn-primary">
                <i class="fas fa-floppy-disk"></i>
                <span>Create profile</span>
            </button>
            <a href="/students" class="btn btn-secondary">
                <i class="fas fa-ban"></i>
                <span>Cancel</span>
            </a>
        </div>
    </form>
</section>
<?php $view->end(); ?>
