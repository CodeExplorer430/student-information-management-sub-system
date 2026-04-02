<?php
/** @var \App\Core\ViewContext $view */

$view->layout('layouts/base', [
    'title' => 'Not Found',
    'pageTitle' => 'Resource not found',
    'pageDescription' => 'The requested page could not be located.',
]);
?>
<?php $view->start('content'); ?>
<section class="module-panel module-panel--slate text-center">
    <div class="empty-state empty-state--spacious">
        <div class="empty-state__icon"><i class="fas fa-circle-exclamation"></i></div>
        <h2>Resource not found</h2>
        <p>The page you requested does not exist or is no longer available.</p>
        <a href="/dashboard" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i>
            <span>Back to dashboard</span>
        </a>
    </div>
</section>
<?php $view->end(); ?>
