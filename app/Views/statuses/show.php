<?php
/** @var AppViewData $app */
/** @var string $csrf */
/** @var StudentRow $student */
/** @var array<int, string> $workflowStatuses */
/** @var array<int, string> $enrollmentStatuses */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$permissions = $app['permissions'];
$view->layout('layouts/base', [
    'title' => 'Student Timeline | ' . $appName,
    'pageTitle' => 'Student Timeline',
    'pageDescription' => $student['first_name'] . ' ' . $student['last_name'] . ' • ' . $student['student_number'],
]);
?>
<?php $view->start('content'); ?>
<div class="row g-4">
    <div class="col-xl-7">
        <section class="module-panel module-panel--purple h-100">
            <div class="module-panel__header">
                <div>
                    <div class="section-pill">Workflow Timeline</div>
                    <h2><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h2>
                    <p><?= e($student['student_number'] . ' • ' . $student['program'] . ' • Enrollment: ' . $student['enrollment_status']) ?></p>
                </div>
                <div class="hero-panel__actions">
                    <a href="/students/<?= e($student['id']) ?>" class="btn btn-outline-primary"><i class="fas fa-arrow-left"></i><span>Back to profile</span></a>
                    <a href="/id-cards" class="btn btn-outline-primary"><i class="fas fa-id-card"></i><span>Generate ID</span></a>
                </div>
            </div>

            <div class="progress-shell">
                <div class="progress-shell__label">
                    <span>Workflow completion</span>
                    <strong><?= e(workflow_progress($student['latest_status'])) ?>%</strong>
                </div>
                <div class="progress">
                    <div class="progress-bar" style="width: <?= e(workflow_progress($student['latest_status'])) ?>%"></div>
                </div>
            </div>

            <div class="timeline">
                <?php if ($student['status_history'] === []): ?>
                    <p class="text-muted mb-0">No status changes recorded.</p>
                <?php else: ?>
                    <?php foreach ($student['status_history'] as $item): ?>
                        <div class="timeline-entry">
                            <div class="timeline-entry__title"><?= e($item['status'] ?? '') ?></div>
                            <div class="timeline-entry__meta"><?= e(($item['created_at'] ?? '') . ' • ' . ($item['assigned_personnel'] ?? 'System')) ?></div>
                            <div class="timeline-entry__text"><?= e($item['remarks'] ?? '') ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-xl-5">
        <section class="module-panel module-panel--amber mb-4">
            <div class="module-panel__header">
                <div>
                    <div class="section-pill">Enrollment History</div>
                    <h2>Student status tracking</h2>
                </div>
            </div>
            <div class="timeline">
                <?php if ($student['enrollment_status_history'] === []): ?>
                    <p class="text-muted mb-0">No enrollment status changes recorded.</p>
                <?php else: ?>
                    <?php foreach ($student['enrollment_status_history'] as $item): ?>
                        <div class="timeline-entry">
                            <div class="timeline-entry__title"><?= e($item['status'] ?? '') ?></div>
                            <div class="timeline-entry__meta"><?= e(($item['created_at'] ?? '') . ' • ' . ($item['assigned_personnel'] ?? 'System')) ?></div>
                            <div class="timeline-entry__text"><?= e($item['remarks'] ?? '') ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <?php if (in_array('statuses.transition', $permissions, true)): ?>
            <section class="module-panel module-panel--purple">
                <div class="module-panel__header">
                    <div>
                        <div class="section-pill">Workflow Controls</div>
                        <h2>Update request status</h2>
                    </div>
                </div>
                <form method="post" action="/statuses/<?= e($student['id']) ?>/transition" class="data-form data-form--compact">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="_back" value="/statuses/<?= e($student['id']) ?>">
                    <div class="form-field">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <?php foreach (($workflowStatuses ?? []) as $status): ?>
                                <option value="<?= e($status) ?>" <?= selected($student['latest_status'], $status) ?>><?= e($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field form-field--full">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" rows="4" required>Updated by workflow review.</textarea>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary"><i class="fas fa-check"></i><span>Apply transition</span></button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if (in_array('statuses.enrollment_transition', $permissions, true)): ?>
            <section class="module-panel module-panel--amber mt-4">
                <div class="module-panel__header">
                    <div>
                        <div class="section-pill">Registrar Controls</div>
                        <h2>Update enrollment status</h2>
                    </div>
                </div>
                <form method="post" action="/statuses/<?= e($student['id']) ?>/enrollment-transition" class="data-form data-form--compact">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="_back" value="/statuses/<?= e($student['id']) ?>">
                    <div class="form-field">
                        <label for="enrollment_status">Enrollment status</label>
                        <select id="enrollment_status" name="enrollment_status" required>
                            <?php foreach (($enrollmentStatuses ?? []) as $status): ?>
                                <option value="<?= e($status) ?>" <?= selected($student['enrollment_status'], $status) ?>><?= e($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field form-field--full">
                        <label for="enrollment_remarks">Remarks</label>
                        <textarea id="enrollment_remarks" name="remarks" rows="4" required>Updated by registrar review.</textarea>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary"><i class="fas fa-check-double"></i><span>Apply enrollment change</span></button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
<?php $view->end(); ?>
