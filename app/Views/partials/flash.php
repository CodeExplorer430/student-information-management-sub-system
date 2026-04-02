<?php
/** @var AppViewData|null $app */
/** @var list<FlashMessage> $flashMessages */

$systemLabel = $app !== null ? $app['name'] : 'System';
$flashMessages = $flashMessages ?? [];
?>
<?php if ($flashMessages !== []): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3 app-toast-stack">
        <?php foreach ($flashMessages as $flash): ?>
            <?php
            $type = $flash['type'];
            $toastType = match ($type) {
                'success' => 'success',
                'warning' => 'info',
                'error', 'danger' => 'danger',
                default => 'info',
            };
            $autoHide = in_array($toastType, ['success', 'info'], true) ? 'true' : 'false';
            $delay = $toastType === 'success' ? '4200' : '5200';
            ?>
            <div class="toast app-toast text-bg-<?= e($toastType) ?> border-0"
                 role="status"
                 aria-live="polite"
                 aria-atomic="true"
                 data-bs-autohide="<?= e($autoHide) ?>"
                 data-bs-delay="<?= e($delay) ?>">
                <div class="toast-header">
                    <span class="app-toast__icon">
                        <i class="fas <?= e(match ($toastType) {
                            'success' => 'fa-circle-check',
                            'danger' => 'fa-circle-xmark',
                            default => 'fa-circle-info',
                        }) ?>"></i>
                    </span>
                    <strong class="me-auto"><?= e(ucfirst($toastType)) ?></strong>
                    <small><?= e($systemLabel) ?></small>
                    <button type="button" class="btn-close ms-2 mb-1" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    <?= e($flash['message']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
