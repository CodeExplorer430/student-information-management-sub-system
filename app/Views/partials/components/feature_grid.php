<?php
/** @var array<int, array<string, string>> $items */

$items = $items ?? [];
?>
<?php if ($items !== []): ?>
    <div class="row row-cols-1 row-cols-lg-3 g-3 app-feature-grid">
        <?php foreach ($items as $item): ?>
            <div class="col">
                <article class="card h-100 app-feature-card">
                    <div class="card-body d-flex gap-3">
                        <div class="app-feature-card__icon">
                            <i class="fas <?= e($item['icon'] ?? 'fa-circle-info') ?>"></i>
                        </div>
                        <div>
                            <h3><?= e($item['title'] ?? '') ?></h3>
                            <p><?= e($item['description'] ?? '') ?></p>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
