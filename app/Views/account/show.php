<?php
/** @var AppViewData $app */
/** @var UserRow $userAccount */
/** @var ValidationErrors $errors */
/** @var string $csrf */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'My Account | ' . $appName,
    'pageTitle' => 'My Account',
    'pageDescription' => 'Update your profile details and avatar for the operations workspace.',
]);
?>
<?php $view->start('content'); ?>
<section class="module-panel module-panel--blue">
    <div class="module-panel__header">
        <div>
            <div class="section-pill">Account</div>
            <h2>My account details</h2>
            <p>Manage your contact information, department label, and profile image used across the workspace shell.</p>
        </div>
    </div>

    <form method="post" action="/account" enctype="multipart/form-data" class="data-form">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="_back" value="/account">
        <?= $view->renderPartial('account/_form', [
            'userAccount' => $userAccount,
            'errors' => $errors ?? [],
            'old' => $old ?? [],
            'submitLabel' => 'Save my account',
        ]) ?>
    </form>
</section>
<?php $view->end(); ?>
