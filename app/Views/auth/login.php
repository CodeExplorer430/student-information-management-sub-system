<?php
/** @var AppViewData $app */
/** @var string $csrf */
/** @var string|null $loginNotice */
/** @var \App\Core\ViewContext $view */

$appName = $app['name'];
$view->layout('layouts/base', [
    'title' => 'Sign In | ' . $appName,
]);
?>
<?php $view->start('auth_content'); ?>
<section class="login-layout">
    <div class="login-brand">
        <div class="login-brand__visual" aria-hidden="true">
            <img src="/assets/branding/bcp-logo.png" alt="">
            <img src="/assets/branding/bcp-logo.png" alt="">
        </div>
        <div class="login-brand__content">
            <div class="login-kicker"><?= e($appName) ?></div>
            <h1>Secure access portal</h1>
            <p>Access the Bestlink College of the Philippines operations and self-service platform for student records, requests, academic visibility, lifecycle tracking, and ID issuance.</p>

            <div class="login-role-grid">
                <span class="login-role-chip"><i class="fas fa-shield-halved"></i> Admin</span>
                <span class="login-role-chip"><i class="fas fa-building-columns"></i> Registrar</span>
                <span class="login-role-chip"><i class="fas fa-clipboard-list"></i> Staff</span>
                <span class="login-role-chip"><i class="fas fa-book-open-reader"></i> Faculty</span>
                <span class="login-role-chip"><i class="fas fa-user-graduate"></i> Student</span>
            </div>

            <div class="login-feature-list">
                <article>
                    <i class="fas fa-user-shield"></i>
                    <div>
                        <h2>Role-based access</h2>
                        <p>Configurable permissions keep governance, operations, faculty access, and student self-service clearly separated.</p>
                    </div>
                </article>
                <article>
                    <i class="fas fa-file-shield"></i>
                    <div>
                        <h2>Auditable operations</h2>
                        <p>Profile changes, request decisions, role updates, and ID issuance remain traceable through audit logging.</p>
                    </div>
                </article>
                <article>
                    <i class="fas fa-layer-group"></i>
                    <div>
                        <h2>Expanded control surface</h2>
                        <p>Dashboards, requests, records, tracking, and ID workflows now run from one responsive institutional workspace.</p>
                    </div>
                </article>
            </div>
        </div>
    </div>

    <div class="login-form-panel">
        <div class="login-form-panel__surface">
            <div class="login-card__header">
                <div class="text-uppercase small fw-semibold text-muted">Authorized Access</div>
                <h2>Sign in to continue</h2>
                <p>Use your institutional account credentials to continue to the secured multi-role operations workspace.</p>
            </div>

            <?php if ($loginNotice !== null): ?>
                <div class="alert alert-info border-0 mb-4" role="status">
                    <i class="fas fa-circle-info me-2"></i>
                    <span><?= e($loginNotice) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="/login" class="login-form row g-3">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="_back" value="/login">
                <div class="col-12">
                    <label class="form-label fw-semibold" for="email">Email</label>
                    <input id="email" type="email" name="email" class="form-control form-control-lg" required autocomplete="username" placeholder="name@bcp.edu">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="password">Password</label>
                    <input id="password" type="password" name="password" class="form-control form-control-lg" required autocomplete="current-password" placeholder="Enter your password">
                </div>
                <div class="col-12 d-grid">
                    <button class="btn btn-primary btn-lg">
                        <i class="fas fa-right-to-bracket"></i>
                        <span>Sign In</span>
                    </button>
                </div>
            </form>
            <div class="login-form-panel__footnote">Protected by configurable RBAC, CSRF validation, secure session controls, and audit logging.</div>
        </div>
    </div>
</section>
<?php $view->end(); ?>
