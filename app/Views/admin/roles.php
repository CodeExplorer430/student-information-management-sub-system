<?php
/** @var AppViewData $app */
/** @var list<RoleRow> $roles */
/** @var list<PermissionRow> $permissions */
/** @var array<string, array<int, string>> $matrix */
/** @var string $csrf */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Role Matrix | ' . $appName,
    'pageTitle' => 'Role Matrix',
    'pageDescription' => 'Review and update permission assignments per role.',
]);
$permissionsByModule = [];
foreach ($permissions as $permission) {
    $permissionsByModule[map_string($permission, 'module')][] = $permission;
}
?>
<?php $view->start('content'); ?>
<section class="admin-workspace admin-workspace--roles">
    <div class="admin-workspace__header">
        <div>
            <div class="section-pill">Governance</div>
            <h2>Role access workspace</h2>
            <p>Adjust each role using human-readable capabilities grouped by area instead of treating every role as a separate card.</p>
        </div>
        <div class="workspace-tab-list" role="tablist" aria-label="Role access tabs" data-tab-list>
            <?php foreach ($roles as $index => $role): ?>
                <button type="button"
                        class="workspace-tab<?= $index === 0 ? ' is-active' : '' ?>"
                        id="role-tab-<?= e($role['slug'] ?? '') ?>"
                        role="tab"
                        aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                        aria-controls="role-panel-<?= e($role['slug'] ?? '') ?>"
                        data-tab-trigger
                        data-tab-target="role-panel-<?= e($role['slug'] ?? '') ?>">
                    <?= e($role['name'] ?? '') ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php foreach ($roles as $role): ?>
        <?php $assigned = $matrix[$role['slug']] ?? []; ?>
        <section class="matrix-role-section"
                 id="role-panel-<?= e($role['slug'] ?? '') ?>"
                 role="tabpanel"
                 aria-labelledby="role-tab-<?= e($role['slug'] ?? '') ?>"
                 data-tab-panel
                 <?= ($roles[0]['slug'] ?? null) === ($role['slug'] ?? null) ? '' : 'hidden' ?>>
            <div class="matrix-role-section__header">
                <div>
                    <div class="section-pill"><?= e($role['slug'] ?? '') ?></div>
                    <h2><?= e($role['name'] ?? '') ?></h2>
                    <p><?= e($role['description'] ?? '') ?></p>
                </div>
                <div class="matrix-role-section__meta">
                    <span><?= e((string) count($assigned)) ?> permissions enabled</span>
                    <span><?= e((string) ($role['user_count'] ?? 0)) ?> assigned users</span>
                </div>
            </div>
            <form method="post" action="/admin/roles/<?= e($role['slug'] ?? '') ?>/permissions" class="matrix-role-form">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="_back" value="/admin/roles">
                <?php foreach ($permissionsByModule as $module => $modulePermissions): ?>
                    <section class="matrix-module-block">
                        <header class="matrix-module-block__header">
                            <h3><?= e(ucwords(str_replace('_', ' ', (string) $module))) ?></h3>
                            <p><?= e((string) count($modulePermissions)) ?> capability options</p>
                        </header>
                        <div class="matrix-permission-list">
                            <?php foreach ($modulePermissions as $permission): ?>
                                <label class="matrix-permission-row">
                                    <input type="checkbox" name="permissions[]" value="<?= e($permission['code']) ?>" <?= in_array($permission['code'], $assigned, true) ? 'checked' : '' ?>>
                                    <span class="matrix-permission-row__copy">
                                        <strong><?= e($permission['label']) ?></strong>
                                        <small><?= e($permission['description']) ?></small>
                                    </span>
                                    <code><?= e($permission['code']) ?></code>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
                <div class="form-actions mt-4">
                    <button class="btn btn-primary">
                        <i class="fas fa-shield-halved"></i>
                        <span>Save role permissions</span>
                    </button>
                </div>
            </form>
        </section>
    <?php endforeach; ?>
</section>
<?php $view->end(); ?>
