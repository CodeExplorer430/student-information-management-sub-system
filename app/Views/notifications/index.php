<?php
/** @var AppViewData $app */
/** @var list<NotificationRow> $notifications */
/** @var string $csrf */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Notifications | ' . $appName,
    'pageTitle' => 'Notification Center',
    'pageDescription' => 'Review in-app updates and recent delivery activity for your requests and records.',
]);
$actionsHtml = $view->capture(static function () use ($csrf): void { ?>
    <form method="post" action="/notifications/mark-all-read">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="_back" value="/notifications">
        <button class="btn btn-outline-primary">
            <i class="fas fa-check-double"></i>
            <span>Mark all as read</span>
        </button>
    </form>
<?php });

$bodyHtml = $view->capture(static function () use ($notifications, $view): void { ?>
    <div class="card app-table-card mt-4">
        <div class="card-body">
            <div class="dashboard-activity-list">
                <?php if ($notifications === []): ?>
                    <?= $view->renderPartial('partials/components/empty_state', [
                        'icon' => 'fa-bell',
                        'title' => 'No notifications yet',
                        'description' => 'New request, queue, and record updates will appear here when they are available.',
                    ]) ?>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <article class="dashboard-activity-item">
                            <div class="dashboard-activity-item__icon"><i class="fas fa-bell"></i></div>
                            <div class="dashboard-activity-item__body">
                                <div class="dashboard-activity-item__title">
                                    <?= e($notification['title'] ?? '') ?>
                                    <?php if ($notification['is_read'] === 0): ?>
                                        <span class="badge bg-primary-lt ms-2">New</span>
                                    <?php endif; ?>
                                </div>
                                <div class="dashboard-activity-item__meta">
                                    <?= e($notification['created_at']) ?>
                                    <?php if ($notification['entity_type'] !== '' && $notification['entity_id'] > 0): ?>
                                        • <?= e(ucfirst($notification['entity_type'])) ?> #<?= e($notification['entity_id']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="dashboard-activity-item__text"><?= e($notification['message']) ?></div>
                                <?php if ($notification['deliveries'] !== []): ?>
                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <?php foreach ($notification['deliveries'] as $delivery): ?>
                                            <?= $view->renderPartial('partials/components/status_badge', [
                                                'label' => strtoupper($delivery['channel']) . ' • ' . $delivery['status'],
                                                'slug' => status_slug($delivery['status']),
                                            ]) ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php }); ?>
<?php $view->start('content'); ?>
<?= $view->renderPartial('partials/components/page_section', [
    'tone' => 'purple',
    'eyebrow' => 'Communications',
    'title' => 'Notification center',
    'description' => 'Track request, queue, and record-related messages with channel delivery visibility.',
    'actionsHtml' => $actionsHtml,
    'bodyHtml' => $bodyHtml,
]) ?>
<?php $view->end(); ?>
