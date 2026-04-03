<?php
/** @var AppViewData $app */
/** @var list<UserRow> $users */
/** @var list<RoleRow> $roles */
/** @var array<int, array<int, string>> $effectivePermissions */
/** @var array<int, array{details: list<array{code: string, label: string, module: string, description: string}>, module_count: int, modules: list<string>}> $effectivePermissionSummaries */
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
            <p>Review active accounts, assign roles, and confirm what each person can access without exposing raw system keys first.</p>
        </div>
    </div>
    <div class="table-shell">
        <table class="table table-modern align-middle mb-0">
            <thead>
            <tr>
                <th>User</th>
                <th>Department</th>
                <th>Roles</th>
                <th>Access Summary</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <?php $permissionSummary = $effectivePermissionSummaries[(int) ($user['id'] ?? 0)] ?? ['details' => [], 'module_count' => 0, 'modules' => []]; ?>
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
                        <div class="admin-access-summary">
                            <div class="admin-access-summary__headline">
                                <strong><?= e((string) count($permissionSummary['details'])) ?> permissions enabled</strong>
                                <span><?= e((string) $permissionSummary['module_count']) ?> access areas</span>
                            </div>
                            <div class="permission-chip-list">
                            <?php foreach (array_slice($permissionSummary['details'], 0, 4) as $permission): ?>
                                <span class="permission-chip" title="<?= e($permission['description']) ?>"><?= e($permission['label']) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($permissionSummary['details']) > 4): ?>
                                <span class="permission-chip permission-chip--muted">+<?= e(count($permissionSummary['details']) - 4) ?> more</span>
                            <?php endif; ?>
                            </div>
                            <?php if ($permissionSummary['modules'] !== []): ?>
                                <div class="admin-access-summary__areas">
                                    <?= e(implode(' • ', array_map(static fn (string $module): string => ucwords(str_replace('_', ' ', $module)), $permissionSummary['modules']))) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($permissionSummary['details'] !== []): ?>
                                <details class="admin-access-summary__details">
                                    <summary>Technical permission keys</summary>
                                    <div class="permission-chip-list mt-2">
                                        <?php foreach ($permissionSummary['details'] as $permission): ?>
                                            <span class="permission-chip permission-chip--muted"><?= e($permission['code']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-2">
                            <a href="/admin/users/<?= e($user['id'] ?? '') ?>/edit" class="btn btn-sm btn-outline-primary">Edit account</a>
                            <button class="btn btn-sm btn-primary" form="user-role-form-<?= e($user['id'] ?? '') ?>">Save roles</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php $view->end(); ?>
