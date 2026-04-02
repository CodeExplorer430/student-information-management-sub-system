<?php
/** @var AppViewData $app */
/** @var StudentRow $student */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Student Profile | ' . $appName,
    'pageTitle' => 'Student Profile',
    'pageDescription' => 'View profile details, timeline history, and audit activity.',
]);
?>
<?php $view->start('content'); ?>
<section class="module-panel module-panel--blue">
    <div class="profile-hero">
        <div class="profile-hero__identity">
            <div class="profile-avatar">
                <?= e(user_initials(trim($student['first_name'] . ' ' . $student['last_name']))) ?>
            </div>
            <div>
                <div class="section-pill">Student profile</div>
                <h2><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h2>
                <p><?= e($student['student_number'] . ' • ' . $student['program'] . ' • Year ' . $student['year_level']) ?></p>
            </div>
        </div>
        <div class="hero-panel__actions">
            <a href="/students/<?= e($student['id']) ?>/edit" class="btn btn-outline-primary">
                <i class="fas fa-user-pen"></i>
                <span>Edit profile</span>
            </a>
            <a href="/records/<?= e($student['id']) ?>" class="btn btn-outline-primary">
                <i class="fas fa-book"></i>
                <span>Academic records</span>
            </a>
            <a href="/statuses/<?= e($student['id']) ?>" class="btn btn-primary">
                <i class="fas fa-timeline"></i>
                <span>Tracking timeline</span>
            </a>
        </div>
    </div>

    <div class="profile-detail-grid">
        <article class="detail-card">
            <h3>Profile information</h3>
            <dl class="detail-list">
                <div><dt>Email</dt><dd><?= e($student['email']) ?></dd></div>
                <div><dt>Phone</dt><dd><?= e($student['phone']) ?></dd></div>
                <div><dt>Birthdate</dt><dd><?= e($student['birthdate']) ?></dd></div>
                <div><dt>Address</dt><dd><?= e($student['address']) ?></dd></div>
                <div><dt>Guardian</dt><dd><?= e($student['guardian_name'] . ' (' . $student['guardian_contact'] . ')') ?></dd></div>
                <div><dt>Department</dt><dd><?= e($student['department']) ?></dd></div>
                <div><dt>Photo</dt><dd><?= !empty($student['photo_path']) ? 'On file' : 'Not uploaded' ?></dd></div>
                <div><dt>Workflow</dt><dd><span class="status-badge status-badge--<?= e(status_slug($student['latest_status'])) ?>"><?= e($student['latest_status']) ?></span></dd></div>
                <div><dt>Enrollment</dt><dd><span class="status-badge status-badge--<?= e(status_slug($student['enrollment_status'])) ?>"><?= e($student['enrollment_status']) ?></span></dd></div>
            </dl>
        </article>

        <article class="detail-card">
            <h3>Workflow timeline</h3>
            <div class="timeline">
                <?php if ($student['status_history'] === []): ?>
                    <p class="text-muted mb-0">No status history yet.</p>
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
        </article>

        <article class="detail-card">
            <h3>Enrollment history</h3>
            <div class="timeline">
                <?php if ($student['enrollment_status_history'] === []): ?>
                    <p class="text-muted mb-0">No enrollment history yet.</p>
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
        </article>
    </div>
</section>

<section class="module-panel module-panel--slate mt-4">
    <div class="module-panel__header">
        <div>
            <div class="section-pill">Audit Trail</div>
            <h2>Profile change history</h2>
        </div>
    </div>
    <div class="table-shell">
        <table class="table table-modern align-middle mb-0">
            <thead><tr><th>Action</th><th>Actor</th><th>Timestamp</th></tr></thead>
            <tbody>
            <?php if ($student['audit_logs'] === []): ?>
                <tr><td colspan="3" class="text-muted">No audit entries yet.</td></tr>
            <?php else: ?>
                <?php foreach ($student['audit_logs'] as $log): ?>
                    <tr>
                        <td><?= e(action_label($log['action'])) ?></td>
                        <td><?= e($log['actor_name'] ?? 'System') ?></td>
                        <td><?= e($log['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php $view->end(); ?>
