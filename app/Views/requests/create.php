<?php
/** @var AppViewData $app */
/** @var StudentRow $student */
/** @var array<int, string> $requestTypes */
/** @var array<int, string> $priorities */
/** @var array<string, mixed> $old */
/** @var string $csrf */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$old = map_value($old ?? []);
$view->layout('layouts/base', [
    'title' => 'Submit Request | ' . $appName,
    'pageTitle' => 'Submit Request',
    'pageDescription' => 'Create a self-service request for registrar or operations review.',
]);
?>
<?php $view->start('content'); ?>
<section class="module-panel module-panel--green">
    <div class="module-panel__header">
        <div>
            <div class="section-pill">Student Workflow</div>
            <h2>Submit a request</h2>
            <p>Requests are tracked through the operational queue with visible status history and assigned personnel.</p>
        </div>
    </div>

    <form method="post" action="/requests" class="data-form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="_back" value="/requests/create">
        <div class="form-grid">
            <div class="form-field">
                <label>Student</label>
                <input value="<?= e($student['first_name'] . ' ' . $student['last_name'] . ' • ' . $student['student_number']) ?>" disabled>
            </div>
            <div class="form-field">
                <label for="request_type">Request Type</label>
                <select id="request_type" name="request_type" required>
                    <option value="">Select request type</option>
                    <?php foreach ($requestTypes as $type): ?>
                        <option value="<?= e($type) ?>" <?= selected(old_input($old, 'request_type'), $type) ?>><?= e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="priority">Priority</label>
                <select id="priority" name="priority" required>
                    <?php foreach ($priorities as $priority): ?>
                        <option value="<?= e($priority) ?>" <?= selected(old_input($old, 'priority', 'Normal'), $priority) ?>><?= e($priority) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="due_at">Target Due Date</label>
                <input id="due_at" name="due_at" type="date" value="<?= e(old_input($old, 'due_at')) ?>">
            </div>
            <div class="form-field form-field--full">
                <label for="title">Title</label>
                <input id="title" name="title" required placeholder="Give the request a short title" value="<?= e(old_input($old, 'title')) ?>">
            </div>
            <div class="form-field form-field--full">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5" required placeholder="Describe the request and any relevant context"><?= e(old_input($old, 'description')) ?></textarea>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary">
                <i class="fas fa-paper-plane"></i>
                <span>Submit request</span>
            </button>
            <a href="/requests" class="btn btn-outline-primary">Cancel</a>
        </div>
    </form>
</section>
<?php $view->end(); ?>
