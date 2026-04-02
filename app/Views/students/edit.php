<?php
/** @var AppViewData $app */
/** @var string $csrf */
/** @var StudentRow $student */
/** @var array<string, array<int, string>> $errors */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Update Student | ' . $appName,
    'pageTitle' => 'Personal Information Update',
    'pageDescription' => 'Update student profile details while preserving an auditable trail of changes.',
]);
?>
<?php $view->start('content'); ?>
<section class="module-panel module-panel--green">
    <div class="module-panel__header">
        <div>
            <div class="section-pill">Personal Information Update</div>
            <h2>Edit student profile</h2>
            <p>Update identity, contact, and guardian information without breaking the current workflow and enrollment history.</p>
        </div>
        <div class="status-badge status-badge--<?= e(status_slug($student['enrollment_status'])) ?>"><?= e($student['enrollment_status']) ?></div>
    </div>

    <div class="feature-grid">
        <article class="feature-item">
            <div class="feature-icon"><i class="fas fa-user-pen"></i></div>
            <div class="feature-text"><h4>Profile maintenance</h4><p>Keep personal and guardian data current for registrar, staff, and self-service student updates.</p></div>
        </article>
        <article class="feature-item">
            <div class="feature-icon"><i class="fas fa-file-signature"></i></div>
            <div class="feature-text"><h4>Auditable changes</h4><p>Every update remains visible in the student audit log and aligned with the existing governance controls.</p></div>
        </article>
        <article class="feature-item">
            <div class="feature-icon"><i class="fas fa-image"></i></div>
            <div class="feature-text"><h4>ID-ready photo</h4><p>Refresh the uploaded image when the student needs a corrected or newer ID card portrait.</p></div>
        </article>
    </div>

    <form method="post" action="/students/<?= e($student['id'] ?? '') ?>/update" enctype="multipart/form-data" class="data-form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="_back" value="/students/<?= e($student['id'] ?? '') ?>/edit">
        <?= $view->renderPartial('students/_form', ['student' => $student ?? [], 'errors' => $errors ?? []]) ?>
        <div class="form-actions">
            <button class="btn btn-primary">
                <i class="fas fa-floppy-disk"></i>
                <span>Save changes</span>
            </button>
            <a href="/students/<?= e($student['id'] ?? '') ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                <span>Back</span>
            </a>
        </div>
    </form>
</section>
<?php $view->end(); ?>
