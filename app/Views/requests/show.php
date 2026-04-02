<?php
/** @var AppViewData $app */
/** @var RequestRow $request */
/** @var array<int, string> $requestStatuses */
/** @var array<int, string> $priorities */
/** @var list<UserRow> $queueUsers */
/** @var array<int, string> $noteVisibilities */
/** @var bool $canManage */
/** @var string $csrf */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Request Detail | ' . $appName,
    'pageTitle' => 'Request Detail',
    'pageDescription' => 'Track request history, ownership, and operational decisioning.',
]);
?>
<?php $view->start('content'); ?>
<div class="row g-4">
    <div class="col-xl-8">
        <section class="module-panel module-panel--blue">
            <div class="module-panel__header">
                <div>
                    <div class="section-pill"><?= e($request['request_type']) ?></div>
                    <h2><?= e($request['title']) ?></h2>
                    <p><?= e($request['first_name'] . ' ' . $request['last_name'] . ' • ' . $request['student_number']) ?></p>
                </div>
                <span class="status-badge status-badge--<?= e(status_slug($request['status'])) ?>"><?= e($request['status']) ?></span>
            </div>
            <div class="detail-grid">
                <div><dt>Description</dt><dd><?= e($request['description']) ?></dd></div>
                <div><dt>Submitted</dt><dd><?= e($request['submitted_at']) ?></dd></div>
                <div><dt>Assigned To</dt><dd><?= e($request['assigned_name'] ?? 'Unassigned') ?></dd></div>
                <div><dt>Raised By</dt><dd><?= e($request['created_by_name']) ?></dd></div>
                <div><dt>Priority</dt><dd><?= e($request['priority']) ?></dd></div>
                <div><dt>Due Target</dt><dd><?= e($request['due_at'] ?? 'Not set') ?></dd></div>
                <div><dt>Resolution Summary</dt><dd><?= e($request['resolution_summary'] ?? 'Pending resolution') ?></dd></div>
            </div>
        </section>

        <section class="module-panel module-panel--purple mt-4">
            <div class="module-panel__header">
                <div>
                    <div class="section-pill">Timeline</div>
                    <h2>Request history</h2>
                </div>
            </div>
            <div class="timeline">
                <?php foreach ($request['history'] as $history): ?>
                    <article class="timeline-entry">
                        <div class="timeline-entry__title"><?= e($history['status'] ?? '') ?></div>
                        <div class="timeline-entry__meta"><?= e(($history['assigned_name'] ?? 'System') . ' • ' . ($history['created_at'] ?? '')) ?></div>
                        <div class="timeline-entry__text"><?= e($history['remarks'] ?? '') ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="module-panel module-panel--green mt-4">
            <div class="module-panel__header">
                <div>
                    <div class="section-pill">Collaboration</div>
                    <h2>Request notes and attachments</h2>
                    <p>Track student-visible updates, operational notes, and linked attachments.</p>
                </div>
            </div>
            <div class="dashboard-activity-list">
                <?php if ($request['notes'] === []): ?>
                    <p class="text-muted mb-0">No request notes yet.</p>
                <?php else: ?>
                    <?php foreach ($request['notes'] as $note): ?>
                        <article class="dashboard-activity-item">
                            <div class="dashboard-activity-item__icon"><i class="fas fa-note-sticky"></i></div>
                            <div class="dashboard-activity-item__body">
                                <div class="dashboard-activity-item__title">
                                    <?= e($note['author_name'] ?? 'User') ?>
                                    <span class="badge bg-secondary-lt ms-2 text-capitalize"><?= e($note['visibility'] ?? 'student') ?></span>
                                </div>
                                <div class="dashboard-activity-item__meta"><?= e($note['created_at'] ?? '') ?></div>
                                <div class="dashboard-activity-item__text"><?= nl2br(e($note['body'] ?? '')) ?></div>
                                <?php foreach ($request['attachments'] as $attachment): ?>
                                    <?php if (($attachment['note_id'] ?? 0) !== $note['id']) {
                                        continue;
                                    } ?>
                                    <div class="mt-2">
                                        <a href="/requests/attachments/<?= e($attachment['id']) ?>/download" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-paperclip"></i>
                                            <span><?= e($attachment['original_name'] ?? 'Attachment') ?></span>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="detail-card">
            <div class="section-pill">Ownership</div>
            <h2 class="mt-3">Queue action</h2>
            <?php if ($canManage): ?>
                <form method="post" action="/requests/<?= e($request['id']) ?>/transition" class="data-form data-form--compact mt-3">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="_back" value="/requests/<?= e($request['id']) ?>">
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <?php foreach ($requestStatuses as $status): ?>
                                    <option value="<?= e($status) ?>" <?= selected($request['status'], $status) ?>><?= e($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="assigned_user_id">Assigned Personnel</label>
                            <select id="assigned_user_id" name="assigned_user_id">
                                <option value="">Unassigned</option>
                                <?php foreach ($queueUsers as $user): ?>
                                    <option value="<?= e($user['id']) ?>" <?= selected($request['assigned_user_id'] ?? '', $user['id']) ?>><?= e($user['name']) ?> (<?= e($user['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="priority">Priority</label>
                            <select id="priority" name="priority">
                                <?php foreach ($priorities as $priority): ?>
                                    <option value="<?= e($priority) ?>" <?= selected($request['priority'], $priority) ?>><?= e($priority) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="due_at">Due Date</label>
                            <input id="due_at" name="due_at" type="date" value="<?= e($request['due_at'] !== null && $request['due_at'] !== '' ? substr($request['due_at'], 0, 10) : '') ?>">
                        </div>
                        <div class="form-field form-field--full">
                            <label for="remarks">Remarks</label>
                            <textarea id="remarks" name="remarks" rows="4" required placeholder="Document the action taken"></textarea>
                        </div>
                        <div class="form-field form-field--full">
                            <label for="resolution_summary">Resolution Summary</label>
                            <textarea id="resolution_summary" name="resolution_summary" rows="3" placeholder="Document the final or current outcome"><?= e($request['resolution_summary'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary">
                            <i class="fas fa-arrows-rotate"></i>
                            <span>Update request</span>
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <p class="text-muted mt-3 mb-0">You can monitor this request here. Operational updates are handled by registrar or staff personnel.</p>
            <?php endif; ?>
        </section>

        <section class="detail-card mt-4">
            <div class="section-pill">Add Update</div>
            <h2 class="mt-3">Post a note</h2>
            <form method="post" action="/requests/<?= e($request['id']) ?>/notes" enctype="multipart/form-data" class="data-form data-form--compact mt-3">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="_back" value="/requests/<?= e($request['id']) ?>">
                <div class="form-grid">
                    <div class="form-field">
                        <label for="visibility">Visibility</label>
                        <select id="visibility" name="visibility">
                            <?php foreach ($noteVisibilities as $visibility): ?>
                                <option value="<?= e($visibility) ?>"><?= e(ucfirst($visibility)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="attachment">Attachment</label>
                        <input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.txt">
                    </div>
                    <div class="form-field form-field--full">
                        <label for="body">Note</label>
                        <textarea id="body" name="body" rows="4" required placeholder="Share the latest handling context or student-visible update"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-outline-primary">
                        <i class="fas fa-note-sticky"></i>
                        <span>Add note</span>
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>
<?php $view->end(); ?>
