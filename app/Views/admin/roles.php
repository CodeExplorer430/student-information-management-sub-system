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
    'pageDescription' => 'Manage role records and permission assignments.',
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
            <p>Maintain dynamic roles and update capability access without using separate role cards.</p>
        </div>
    </div>

    <div class="role-management-grid">
        <aside class="role-management-panel">
            <div class="role-management-panel__header">
                <div>
                    <h3>Role registry</h3>
                    <p><?= e((string) count($roles)) ?> dynamic roles available for user assignment.</p>
                </div>
            </div>

            <div class="role-registry-table" role="tablist" aria-label="Role registry" data-tab-list>
                <?php foreach ($roles as $index => $role): ?>
                    <?php $assigned = $matrix[$role['slug']] ?? []; ?>
                    <button type="button"
                            class="role-registry-row<?= $index === 0 ? ' is-active' : '' ?>"
                            id="role-tab-<?= e($role['slug'] ?? '') ?>"
                            role="tab"
                            aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                            aria-controls="role-panel-<?= e($role['slug'] ?? '') ?>"
                            data-tab-trigger
                            data-tab-target="role-panel-<?= e($role['slug'] ?? '') ?>">
                        <span>
                            <strong><?= e($role['name'] ?? '') ?></strong>
                            <small><?= e($role['slug'] ?? '') ?></small>
                        </span>
                        <span><?= e((string) count($assigned)) ?> permissions</span>
                        <span><?= e((string) ($role['user_count'] ?? 0)) ?> users</span>
                    </button>
                <?php endforeach; ?>
            </div>

            <form method="post" action="/admin/roles" class="role-editor-form">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="_back" value="/admin/roles">
                <h3>Add role</h3>
                <div class="form-field">
                    <label for="role-name">Role name</label>
                    <input id="role-name" name="name" placeholder="Student Services" required>
                </div>
                <div class="form-field">
                    <label for="role-slug">Role slug</label>
                    <input id="role-slug" name="slug" placeholder="student_services" maxlength="50" required>
                </div>
                <div class="form-field">
                    <label for="role-description">Description</label>
                    <textarea id="role-description" name="description" rows="3" placeholder="Describe what this role is responsible for."></textarea>
                </div>
                <button class="btn btn-outline-primary">
                    <i class="fas fa-plus"></i>
                    <span>Create role</span>
                </button>
            </form>
        </aside>

        <div class="role-permission-workspace">
            <?php foreach ($roles as $role): ?>
                <?php $assigned = $matrix[$role['slug']] ?? []; ?>
                <section class="role-permission-panel"
                         id="role-panel-<?= e($role['slug'] ?? '') ?>"
                         role="tabpanel"
                         aria-labelledby="role-tab-<?= e($role['slug'] ?? '') ?>"
                         data-tab-panel
                         <?= ($roles[0]['slug'] ?? null) === ($role['slug'] ?? null) ? '' : 'hidden' ?>>
                    <div class="role-permission-panel__header">
                        <div>
                            <div class="section-pill"><?= e($role['slug'] ?? '') ?></div>
                            <h2><?= e($role['name'] ?? '') ?></h2>
                            <p><?= e($role['description'] ?? '') ?></p>
                        </div>
                        <div class="role-permission-panel__meta">
                            <span><?= e((string) count($assigned)) ?> permissions enabled</span>
                            <span><?= e((string) ($role['user_count'] ?? 0)) ?> assigned users</span>
                        </div>
                    </div>

                    <form method="post" action="/admin/roles/<?= e($role['slug'] ?? '') ?>/update" class="role-editor-form role-editor-form--inline">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="_back" value="/admin/roles">
                        <div class="form-field">
                            <label for="edit-role-name-<?= e($role['id'] ?? '') ?>">Role name</label>
                            <input id="edit-role-name-<?= e($role['id'] ?? '') ?>" name="name" value="<?= e($role['name'] ?? '') ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="edit-role-slug-<?= e($role['id'] ?? '') ?>">Role slug</label>
                            <input id="edit-role-slug-<?= e($role['id'] ?? '') ?>" value="<?= e($role['slug'] ?? '') ?>" readonly>
                            <small>Role slugs are immutable after creation.</small>
                        </div>
                        <div class="form-field form-field--full">
                            <label for="edit-role-description-<?= e($role['id'] ?? '') ?>">Description</label>
                            <textarea id="edit-role-description-<?= e($role['id'] ?? '') ?>" name="description" rows="2"><?= e($role['description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-actions">
                            <button class="btn btn-outline-primary">
                                <i class="fas fa-pen"></i>
                                <span>Save role details</span>
                            </button>
                        </div>
                    </form>

                    <form method="post" action="/admin/roles/<?= e($role['slug'] ?? '') ?>/permissions" class="matrix-role-form">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="_back" value="/admin/roles">
                        <div class="permission-module-table">
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
                        </div>
                        <div class="form-actions mt-4">
                            <button class="btn btn-primary">
                                <i class="fas fa-shield-halved"></i>
                                <span>Save role permissions</span>
                            </button>
                        </div>
                    </form>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php $view->end(); ?>
