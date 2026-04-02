<?php
/** @var string $label */
/** @var string|null $slug */
/** @var string $class */

$label = string_value($label ?? '');
$slug = string_value($slug ?? status_slug($label), status_slug($label));
$class = string_value($class ?? '');
?>
<span class="badge rounded-pill status-badge status-badge--<?= e($slug) ?> <?= e($class) ?>">
    <?= e($label) ?>
</span>
