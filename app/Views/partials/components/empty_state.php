<?php
/** @var string $icon */
/** @var string $title */
/** @var string $description */
/** @var string $class */

$icon = string_value($icon ?? 'fa-inbox', 'fa-inbox');
$title = string_value($title ?? 'Nothing to show', 'Nothing to show');
$description = string_value($description ?? '');
$class = string_value($class ?? '');
?>
<div class="app-empty-state <?= e($class) ?>">
    <div class="app-empty-state__icon">
        <i class="fas <?= e($icon) ?>"></i>
    </div>
    <div class="app-empty-state__title"><?= e($title) ?></div>
    <?php if ($description !== ''): ?>
        <div class="app-empty-state__text"><?= e($description) ?></div>
    <?php endif; ?>
</div>
