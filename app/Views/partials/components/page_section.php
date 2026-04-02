<?php
/** @var \App\Core\ViewContext $view */
/** @var string $tone */
/** @var string $eyebrow */
/** @var string $title */
/** @var string $description */
/** @var string $actionsHtml */
/** @var array<int, array<string, string>> $features */
/** @var string $bodyHtml */
/** @var string $class */

$tone = string_value($tone ?? 'slate', 'slate');
$features = is_array($features ?? null) ? $features : [];
$bodyHtml = string_value($bodyHtml ?? '');
$class = string_value($class ?? '');
?>
<section class="card app-section app-section--<?= e($tone) ?> <?= e($class) ?>">
    <div class="card-body app-section__body">
        <?= $view->renderPartial('partials/components/page_header', [
            'eyebrow' => $eyebrow ?? '',
            'title' => $title ?? '',
            'description' => $description ?? '',
            'actionsHtml' => $actionsHtml ?? '',
        ]) ?>
        <?= $view->renderPartial('partials/components/feature_grid', ['items' => $features]) ?>
        <?= $bodyHtml ?>
    </div>
</section>
