<?php
/** @var AppViewData $app */
/** @var UserRow $userAccount */
/** @var list<RoleRow> $roles */
/** @var array{details: list<array{code: string, label: string, module: string, description: string}>, module_count: int, modules: list<string>} $permissionSummary */
/** @var ValidationErrors $errors */
/** @var string $csrf */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Edit User Account | ' . $appName,
    'pageTitle' => 'Edit User Account',
    'pageDescription' => 'Manage profile details, role assignments, and admin password reset for this account.',
]);
?>
<?php $view->start('content'); ?>
<section class="module-panel module-panel--purple">
    <div class="module-panel__header">
        <div>
            <div class="section-pill">Governance</div>
            <h2><?= e($userAccount['name'] ?? '') ?></h2>
            <p>Update account details, role assignments, and admin-only password reset controls from one user workspace.</p>
        </div>
        <div class="hero-panel__actions">
            <a href="/admin/users" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i>
                <span>Back to users</span>
            </a>
        </div>
    </div>

    <div class="admin-account-grid">
        <section class="detail-card">
            <h3>Account details</h3>
            <form method="post" action="/admin/users/<?= e($userAccount['id']) ?>/update" enctype="multipart/form-data" class="data-form">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="_back" value="/admin/users/<?= e($userAccount['id']) ?>/edit">
                <?= $view->renderPartial('account/_form', [
                    'userAccount' => $userAccount,
                    'errors' => $errors ?? [],
                    'old' => $old ?? [],
                    'submitLabel' => 'Save user account',
                ]) ?>
            </form>
        </section>

        <section class="detail-card">
            <div class="detail-card__eyebrow">Access</div>
            <h3>Role assignments</h3>
            <form method="post" action="/admin/users/<?= e($userAccount['id']) ?>/role" class="d-grid gap-3">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="_back" value="/admin/users/<?= e($userAccount['id']) ?>/edit">
                <select name="role" class="form-select">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= e($role['slug']) ?>" <?= selected($userAccount['role'] ?? '', $role['slug']) ?>><?= e($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="small text-muted">Options are loaded from roles that admins manage in the role matrix.</div>
                <button class="btn btn-outline-primary">
                    <i class="fas fa-shield-halved"></i>
                    <span>Save role assignment</span>
                </button>
            </form>

            <div class="admin-access-summary mt-4">
                <div class="admin-access-summary__headline">
                    <strong><?= e((string) count($permissionSummary['details'])) ?> permissions enabled</strong>
                    <span><?= e((string) $permissionSummary['module_count']) ?> access areas</span>
                </div>
                <div class="permission-chip-list">
                    <?php foreach (array_slice($permissionSummary['details'], 0, 6) as $permission): ?>
                        <span class="permission-chip" title="<?= e($permission['description']) ?>"><?= e($permission['label']) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($permissionSummary['details']) > 6): ?>
                        <span class="permission-chip permission-chip--muted">+<?= e(count($permissionSummary['details']) - 6) ?> more</span>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</section>

<section class="module-panel module-panel--slate mt-4">
    <div class="module-panel__header">
        <div>
            <div class="section-pill">Security</div>
            <h2>Admin password reset</h2>
            <p>Set a new password for this account without changing any other profile details.</p>
        </div>
    </div>
    <form method="post" action="/admin/users/<?= e($userAccount['id']) ?>/password" class="data-form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="_back" value="/admin/users/<?= e($userAccount['id']) ?>/edit">
        <div class="form-grid">
            <div class="form-field">
                <label for="password">New password</label>
                <input id="password" type="password" name="password" required>
                <?php if (($error = first_error($errors ?? [], 'password')) !== null): ?>
                    <div class="field-error"><?= e($error) ?></div>
                <?php endif; ?>
            </div>
            <div class="form-field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required>
                <?php if (($error = first_error($errors ?? [], 'password_confirmation')) !== null): ?>
                    <div class="field-error"><?= e($error) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-actions mt-4">
            <button class="btn btn-primary">
                <i class="fas fa-key"></i>
                <span>Reset password</span>
            </button>
        </div>
    </form>
</section>
<?php $view->end(); ?>
