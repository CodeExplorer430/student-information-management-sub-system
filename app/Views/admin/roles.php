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
?>
<?php $view->start('content'); ?>
<div class="row g-4">
    <?php foreach ($roles as $role): ?>
        <?php $assigned = $matrix[$role['slug']] ?? []; ?>
        <div class="col-xl-6">
            <section class="detail-card h-100">
                <div class="section-pill"><?= e($role['slug'] ?? '') ?></div>
                <h2 class="mt-3"><?= e($role['name'] ?? '') ?></h2>
                <p class="text-muted"><?= e($role['description'] ?? '') ?></p>
                <form method="post" action="/admin/roles/<?= e($role['slug'] ?? '') ?>/permissions" class="mt-4">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="_back" value="/admin/roles">
                    <div class="permission-grid">
                        <?php foreach ($permissions as $permission): ?>
                            <label class="permission-item">
                                <input type="checkbox" name="permissions[]" value="<?= e($permission['code']) ?>" <?= in_array($permission['code'], $assigned, true) ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= e($permission['label']) ?></strong>
                                    <small><?= e($permission['module'] . ' • ' . $permission['description']) ?></small>
                                </span>
                            </label>
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
        </div>
    <?php endforeach; ?>
</div>
<?php $view->end(); ?>
