<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\IdCardController;
use App\Controllers\NotificationController;
use App\Controllers\RecordController;
use App\Controllers\ReportController;
use App\Controllers\RequestController;
use App\Controllers\StatusController;
use App\Controllers\StudentController;
use App\Core\Router;

/** @var Router $router */
$router->get('/health/live', [HealthController::class, 'live']);
$router->get('/health/ready', [HealthController::class, 'ready']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

$router->get('/', [DashboardController::class, 'index'], ['auth']);
$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);

$router->get('/students', [StudentController::class, 'index'], ['auth', 'permission:students.view']);
$router->get('/students/create', [StudentController::class, 'create'], ['auth', 'permission:students.create']);
$router->post('/students', [StudentController::class, 'store'], ['auth', 'permission:students.create', 'csrf']);
$router->get('/students/[i:id]', [StudentController::class, 'show'], ['auth', 'permission:students.view']);
$router->get('/students/[i:id]/edit', [StudentController::class, 'edit'], ['auth', 'permission:students.update']);
$router->post('/students/[i:id]/update', [StudentController::class, 'update'], ['auth', 'permission:students.update', 'csrf']);

$router->get('/records', [RecordController::class, 'index'], ['auth', 'permission:records.view']);
$router->get('/records/[i:id]', [RecordController::class, 'show'], ['auth', 'permission:records.view']);
$router->get('/records/[i:id]/export', [RecordController::class, 'export'], ['auth', 'permission:records.view']);

$router->get('/statuses', [StatusController::class, 'index'], ['auth', 'permission:statuses.view']);
$router->get('/statuses/[i:id]', [StatusController::class, 'show'], ['auth', 'permission:statuses.view']);
$router->post('/statuses/[i:id]/transition', [StatusController::class, 'transition'], ['auth', 'permission:statuses.transition', 'csrf']);
$router->post('/statuses/[i:id]/enrollment-transition', [StatusController::class, 'transitionEnrollment'], ['auth', 'permission:statuses.enrollment_transition', 'csrf']);

$router->get('/id-cards', [IdCardController::class, 'index'], ['auth', 'permission:id_cards.view']);
$router->post('/id-cards/generate', [IdCardController::class, 'generate'], ['auth', 'permission:id_cards.generate', 'csrf']);
$router->get('/id-cards/[i:id]/download', [IdCardController::class, 'download'], ['auth', 'permission:id_cards.view']);
$router->get('/id-cards/[i:id]/print', [IdCardController::class, 'printView'], ['auth', 'permission:id_cards.view']);
$router->get('/id-cards/[i:id]/verify', [IdCardController::class, 'verify']);

$router->get('/requests', [RequestController::class, 'index'], ['auth']);
$router->get('/requests/create', [RequestController::class, 'create'], ['auth', 'permission:requests.create']);
$router->post('/requests', [RequestController::class, 'store'], ['auth', 'permission:requests.create', 'csrf']);
$router->get('/requests/[i:id]', [RequestController::class, 'show'], ['auth']);
$router->post('/requests/[i:id]/transition', [RequestController::class, 'transition'], ['auth', 'permission:requests.transition', 'csrf']);
$router->post('/requests/[i:id]/notes', [RequestController::class, 'addNote'], ['auth', 'csrf']);
$router->get('/requests/attachments/[i:attachmentId]/download', [RequestController::class, 'downloadAttachment'], ['auth']);

$router->get('/notifications', [NotificationController::class, 'index'], ['auth']);
$router->post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'], ['auth', 'csrf']);

$router->get('/admin/users', [AdminController::class, 'users'], ['auth', 'permission:admin.users']);
$router->post('/admin/users/[i:id]/role', [AdminController::class, 'updateUserRole'], ['auth', 'permission:admin.users', 'csrf']);
$router->get('/admin/roles', [AdminController::class, 'roles'], ['auth', 'permission:admin.roles']);
$router->post('/admin/roles/[a:slug]/permissions', [AdminController::class, 'syncRolePermissions'], ['auth', 'permission:admin.roles', 'csrf']);
$router->get('/admin/diagnostics', [AdminController::class, 'diagnostics'], ['auth', 'permission:admin.roles']);

$router->get('/reports', [ReportController::class, 'index'], ['auth', 'permission:reports.view']);
$router->get('/reports/export/[a:dataset]', [ReportController::class, 'export'], ['auth', 'permission:reports.view']);
