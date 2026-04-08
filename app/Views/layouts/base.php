<?php
/** @var AppViewData $app */
/** @var string $csrf */
/** @var string $current_path */
/** @var string|null $title */
/** @var string|null $pageTitle */
/** @var string|null $pageDescription */
/** @var \App\Core\ViewContext $view */

$user = $app['user'];
$appName = $app['name'];
$title = $title ?? $appName;
$pageTitle = $pageTitle ?? $appName;
$pageDescription = $pageDescription ?? 'Secure student profile operations.';
$content = $view->section('content');
$authContent = $view->section('auth_content', $content);
$role = $user !== null ? $user['role'] : '';
$userName = $user !== null ? $user['name'] : 'Guest';
$currentPath = $current_path ?? '/';
$permissions = $app['permissions'];
$notificationUnreadCount = $app['notification_unread_count'];
$bodyClass = $user !== null ? 'authenticated-shell role-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) $role) : 'guest-shell';
$userAvatar = $user !== null ? private_upload_data_uri(nullable_string_value($user['photo_path'] ?? null)) : '';

$navItems = [
    ['label' => 'Dashboard', 'href' => '/dashboard', 'icon' => 'fa-gauge-high', 'active' => $currentPath === '/dashboard' || $currentPath === '/', 'permissions' => []],
    ['label' => 'My Account', 'href' => '/account', 'icon' => 'fa-user-circle', 'active' => str_starts_with($currentPath, '/account'), 'permissions' => []],
    ['label' => 'Student Profiles', 'href' => '/students', 'icon' => 'fa-user-graduate', 'active' => str_starts_with($currentPath, '/students'), 'permissions' => ['students.view', 'students.view_own']],
    ['label' => 'Academic Records', 'href' => '/records', 'icon' => 'fa-book-open-reader', 'active' => str_starts_with($currentPath, '/records'), 'permissions' => ['records.view', 'records.view_own']],
    ['label' => 'Status Tracking', 'href' => '/statuses', 'icon' => 'fa-chart-line', 'active' => str_starts_with($currentPath, '/statuses'), 'permissions' => ['statuses.view', 'statuses.view_own']],
    ['label' => 'Request Center', 'href' => '/requests', 'icon' => 'fa-list-check', 'active' => str_starts_with($currentPath, '/requests'), 'permissions' => ['requests.view_queue', 'requests.view_own']],
    ['label' => 'Notifications', 'href' => '/notifications', 'icon' => 'fa-bell', 'active' => str_starts_with($currentPath, '/notifications'), 'permissions' => []],
    ['label' => 'ID Generation', 'href' => '/id-cards', 'icon' => 'fa-id-card', 'active' => str_starts_with($currentPath, '/id-cards'), 'permissions' => ['id_cards.view', 'id_cards.view_own']],
    ['label' => 'Reports', 'href' => '/reports', 'icon' => 'fa-chart-pie', 'active' => str_starts_with($currentPath, '/reports'), 'permissions' => ['reports.view']],
    ['label' => 'Admin Users', 'href' => '/admin/users', 'icon' => 'fa-users-gear', 'active' => str_starts_with($currentPath, '/admin/users'), 'permissions' => ['admin.users']],
    ['label' => 'Role Matrix', 'href' => '/admin/roles', 'icon' => 'fa-shield-halved', 'active' => str_starts_with($currentPath, '/admin/roles'), 'permissions' => ['admin.roles']],
    ['label' => 'Diagnostics', 'href' => '/admin/diagnostics', 'icon' => 'fa-wave-square', 'active' => str_starts_with($currentPath, '/admin/diagnostics'), 'permissions' => ['admin.roles']],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <meta name="application-name" content="<?= e($appName) ?>">
    <meta name="apple-mobile-web-app-title" content="<?= e($appName) ?>">
    <meta name="theme-color" content="#132848">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <script>
        (() => {
            try {
                const saved = JSON.parse(localStorage.getItem('sims-accessibility') || '{}');
                const root = document.documentElement;
                const textSize = typeof saved.textSize === 'string' ? saved.textSize : 'default';
                const contrast = typeof saved.contrast === 'string' ? saved.contrast : 'default';
                const motion = typeof saved.motion === 'string'
                    ? saved.motion
                    : (window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'reduced' : 'default');

                root.dataset.textSize = textSize;
                root.dataset.contrast = contrast;
                root.dataset.motion = motion;
            } catch (error) {
                document.documentElement.dataset.textSize = 'default';
                document.documentElement.dataset.contrast = 'default';
                document.documentElement.dataset.motion = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'reduced' : 'default';
            }
        })();
    </script>
    <link rel="stylesheet" href="/assets/vendor/tabler/tabler.min.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="/assets/vendor/fontawesome/css/solid.min.css">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="<?= e($bodyClass) ?>" data-bs-theme="light">
<?php if ($user !== null): ?>
    <div class="overlay" data-sidebar-overlay></div>
    <div class="app-shell app-shell--tabler">
        <aside class="sidebar" id="app-sidebar">
            <div class="sidebar-header">
                <a href="/dashboard" class="sidebar-brand">
                    <span class="sidebar-brand__mark sidebar-brand__mark--logo">
                        <img src="/assets/branding/bcp-logo.png" alt="Bestlink College of the Philippines logo">
                    </span>
                    <span class="sidebar-brand__text">
                        <strong><?= e($appName) ?></strong>
                        <small>Bestlink College of the Philippines operations workspace</small>
                    </span>
                </a>
            </div>

            <nav class="sidebar-nav">
                <?php foreach ($navItems as $item): ?>
                    <?php
                    $requiredPermissions = $item['permissions'];
                    $visible = $requiredPermissions === [];
                    foreach ($requiredPermissions as $permission) {
                        if (in_array($permission, $permissions, true)) {
                            $visible = true;
                            break;
                        }
                    }
                    ?>
                    <?php if (!$visible): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <a class="nav-item<?= $item['active'] ? ' active' : '' ?>" href="<?= e($item['href']) ?>">
                        <i class="fas <?= e($item['icon']) ?>"></i>
                        <span><?= e($item['label']) ?></span>
                        <?php if ($item['href'] === '/notifications' && $notificationUnreadCount > 0): ?>
                            <span class="badge bg-primary-lt ms-auto"><?= e($notificationUnreadCount) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="user-avatar user-avatar--sidebar">
                        <?php if ($userAvatar !== ''): ?>
                            <img src="<?= e($userAvatar) ?>" alt="<?= e($userName) ?>">
                        <?php else: ?>
                            <?= e(user_initials($userName)) ?>
                        <?php endif; ?>
                    </div>
                    <div class="sidebar-user__meta">
                        <div class="fw-semibold"><?= e($userName) ?></div>
                        <div class="small text-uppercase"><?= e((string) $role) ?></div>
                    </div>
                </div>
                <form method="post" action="/logout" class="mt-3">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="_back" value="<?= e($currentPath) ?>">
                    <button class="btn btn-light w-100">
                        <i class="fas fa-right-from-bracket"></i>
                        <span>Sign out</span>
                    </button>
                </form>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-bar">
                <div class="page-heading">
                    <button type="button" class="shell-toggle" data-sidebar-toggle aria-label="Toggle navigation">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1><?= e($pageTitle) ?></h1>
                        <p><?= e($pageDescription) ?></p>
                        <div class="page-heading__brand">
                            <img src="/assets/branding/bcp-logo.png" alt="Bestlink College of the Philippines logo">
                            <span>Bestlink College of the Philippines</span>
                        </div>
                    </div>
                </div>
                <div class="user-info">
                    <button type="button"
                            class="btn btn-outline-secondary accessibility-trigger"
                            data-accessibility-toggle
                            aria-expanded="false"
                            aria-controls="accessibility-panel">
                        <i class="fas fa-universal-access"></i>
                        <span>Accessibility</span>
                    </button>
                    <a href="/notifications" class="btn btn-icon btn-outline-secondary position-relative" aria-label="Open notifications">
                        <i class="fas fa-bell"></i>
                        <?php if ($notificationUnreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= e($notificationUnreadCount) ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/account" class="account-menu-link" aria-label="Open my account">
                        <div class="text-end">
                            <div class="fw-semibold"><?= e($userName) ?></div>
                            <div class="text-muted small text-capitalize"><?= e((string) $role) ?></div>
                        </div>
                        <div class="user-avatar">
                            <?php if ($userAvatar !== ''): ?>
                                <img src="<?= e($userAvatar) ?>" alt="<?= e($userName) ?>">
                            <?php else: ?>
                                <?= e(user_initials($userName)) ?>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
            </header>

            <div class="content-container">
                <?= $view->renderPartial('partials/flash') ?>
                <div class="page-transition">
                    <?= $content ?>
                </div>
            </div>
        </main>
    </div>
<?php else: ?>
    <div class="auth-shell">
        <div class="auth-backdrop"></div>
        <div class="auth-panel">
            <div class="auth-utility-bar">
                <button type="button"
                        class="btn btn-outline-secondary accessibility-trigger"
                        data-accessibility-toggle
                        aria-expanded="false"
                        aria-controls="accessibility-panel">
                    <i class="fas fa-universal-access"></i>
                    <span>Accessibility</span>
                </button>
            </div>
            <?= $view->renderPartial('partials/flash') ?>
            <?= $authContent ?>
        </div>
    </div>
<?php endif; ?>
<?= $view->renderPartial('partials/accessibility') ?>
<script src="/assets/vendor/tabler/tabler.min.js"></script>
<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/app.js"></script>
</body>
</html>
