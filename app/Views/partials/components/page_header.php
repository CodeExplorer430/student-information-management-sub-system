<?php
/** @var string $eyebrow */
/** @var string $title */
/** @var string $description */
/** @var string $actionsHtml */
/** @var string $class */

$eyebrow = string_value($eyebrow ?? '');
$title = string_value($title ?? '');
$description = string_value($description ?? '');
$actionsHtml = string_value($actionsHtml ?? '');
$class = string_value($class ?? '');
?>
<div class="app-page-header <?= e($class) ?>">
    <div class="app-page-header__copy">
        <?php if ($eyebrow !== ''): ?>
            <div class="section-pill"><?= e($eyebrow) ?></div>
        <?php endif; ?>
        <?php if ($title !== ''): ?>
            <h2><?= e($title) ?></h2>
        <?php endif; ?>
        <?php if ($description !== ''): ?>
            <p><?= e($description) ?></p>
        <?php endif; ?>
    </div>
    <?php if (trim($actionsHtml) !== ''): ?>
        <div class="app-page-header__actions">
            <?= $actionsHtml ?>
        </div>
    <?php endif; ?>
</div>
