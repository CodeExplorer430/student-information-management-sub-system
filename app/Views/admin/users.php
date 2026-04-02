<?php
/** @var AppViewData $app */
/** @var list<UserRow> $users */
/** @var list<RoleRow> $roles */
/** @var array<int, array<int, string>> $effectivePermissions */
/** @var string $csrf */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Admin Users | ' . $appName,
    'pageTitle' => 'Admin Users',
    'pageDescription' => 'Manage system users and assign operational roles.',
]);
?>
<?php $view->start('content'); ?>
<section class="module-panel module-panel--purple">
    <div class="module-panel__header">
        <div>
            <div class="section-pill">Governance</div>
            <h2>User role assignment</h2>
            <p>Review active accounts and map them to the appropriate operational role.</p>
        </div>
    </div>
    <div class="table-shell">
        <table class="table table-modern align-middle mb-0">
            <thead>
            <tr>
                <th>User</th>
                <th>Department</th>
                <th>Roles</th>
                <th>Effective Permissions</th>
                <th class="text-end">Update</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($user['name'] ?? '') ?></div>
                        <div class="small text-muted"><?= e($user['email'] ?? '') ?></div>
                    </td>
                    <td><?= e($user['department'] ?? '') ?></td>
                    <td>
                        <form id="user-role-form-<?= e($user['id'] ?? '') ?>" method="post" action="/admin/users/<?= e($user['id'] ?? '') ?>/role" class="d-grid gap-2">
                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="_back" value="/admin/users">
                            <select name="roles[]" class="form-select form-select-sm" multiple size="<?= e(max(3, min(5, count($roles)))) ?>">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= e($role['slug']) ?>" <?= in_array($role['slug'], $user['roles'], true) ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="small text-muted mt-1">Primary display role: <?= e($user['role']) ?></div>
                        </form>
                    </td>
                    <td>
                        <div class="permission-chip-list">
                            <?php foreach (array_slice($effectivePermissions[$user['id']] ?? [], 0, 4) as $permission): ?>
                                <span class="permission-chip"><?= e($permission) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($effectivePermissions[$user['id']] ?? []) > 4): ?>
                                <span class="permission-chip permission-chip--muted">+<?= e(count($effectivePermissions[$user['id']] ?? []) - 4) ?> more</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-primary" form="user-role-form-<?= e($user['id'] ?? '') ?>">Save</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php $view->end(); ?>
