<?php

declare(strict_types=1);

use App\Controllers\AccountController;
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
use App\Core\Application;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\HttpEmitter;
use App\Core\HttpKernel;
use App\Core\Logger;
use App\Core\RequestContext;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Repositories\AcademicRecordRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\RequestRepository;
use App\Repositories\RoleRepository;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;
use App\Services\AccountService;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\BackupStatusService;
use App\Services\DashboardService;
use App\Services\EnrollmentStatusService;
use App\Services\FileStorageService;
use App\Services\HealthService;
use App\Services\IdCardService;
use App\Services\NotificationService;
use App\Services\OpsAlertService;
use App\Services\ReportService;
use App\Services\RequestService;
use App\Services\S3BackupRemoteStore;
use App\Services\SearchService;
use App\Services\StatusService;
use App\Services\StudentService;
use Dotenv\Dotenv;

$root = dirname(__DIR__);

if (file_exists($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$appName = env('APP_NAME', 'Bestlink SIS');
$appName = $appName === 'Student Information Management' ? 'Bestlink SIS' : $appName;
$emailFromName = env('NOTIFY_EMAIL_FROM_NAME', 'Bestlink SIS');
$emailFromName = $emailFromName === 'Student Information Management' ? 'Bestlink SIS' : $emailFromName;

$app = new Application($root);

$app->singleton(Config::class, static fn (): Config => new Config([
    'app' => [
        'name' => $appName,
        'env' => env('APP_ENV', 'production'),
        'debug' => env('APP_DEBUG', 'false') === 'true',
        'url' => env('APP_URL', 'http://127.0.0.1:8000'),
        'key' => env('APP_KEY', 'change-me'),
        'timezone' => env('APP_TIMEZONE', 'UTC'),
        'version' => env('APP_VERSION', ''),
    ],
    'db' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'student_information_management'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
    ],
    'session' => [
        'name' => env('SESSION_NAME', 'simssession'),
        'lifetime' => (int) env('SESSION_LIFETIME', '120'),
        'path' => $root . '/storage/framework/sessions',
        'same_site' => env('SESSION_SAME_SITE', 'Lax'),
        'secure' => env('SESSION_SECURE', str_starts_with((string) env('APP_URL', 'http://127.0.0.1:8000'), 'https://') ? 'true' : 'false') === 'true',
    ],
    'security' => [
        'default_password' => env('DEFAULT_PASSWORD', 'Password123!'),
    ],
    'backup' => [
        'export_key' => env('BACKUP_EXPORT_KEY', ''),
        'storage_path' => env('BACKUP_STORAGE_PATH', $root . '/storage/backups'),
        'max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', '24'),
        'remote_max_age_hours' => (int) env('BACKUP_REMOTE_MAX_AGE_HOURS', '24'),
        'drill_max_age_hours' => (int) env('BACKUP_DRILL_MAX_AGE_HOURS', '168'),
        'remote' => [
            'driver' => env('BACKUP_REMOTE_DRIVER', ''),
            'bucket' => env('BACKUP_REMOTE_BUCKET', ''),
            'region' => env('BACKUP_REMOTE_REGION', ''),
            'endpoint' => env('BACKUP_REMOTE_ENDPOINT', ''),
            'access_key' => env('BACKUP_REMOTE_ACCESS_KEY', ''),
            'secret_key' => env('BACKUP_REMOTE_SECRET_KEY', ''),
            'prefix' => env('BACKUP_REMOTE_PREFIX', ''),
            'path_style' => env('BACKUP_REMOTE_PATH_STYLE', 'false') === 'true',
        ],
    ],
    'notifications' => [
        'email_driver' => env('NOTIFY_EMAIL_DRIVER', 'log'),
        'email_from_address' => env('NOTIFY_EMAIL_FROM_ADDRESS', 'noreply@bcp.edu'),
        'email_from_name' => $emailFromName,
        'sms_driver' => env('NOTIFY_SMS_DRIVER', 'log'),
        'sms_api_url' => env('NOTIFY_SMS_API_URL', ''),
        'sms_api_token' => env('NOTIFY_SMS_API_TOKEN', ''),
        'sms_sender_id' => env('NOTIFY_SMS_SENDER_ID', 'BCP'),
    ],
]));

$app->singleton(RequestContext::class, static fn (): RequestContext => new RequestContext());

$app->singleton(Logger::class, static fn (Application $app): Logger => new Logger(
    $app->rootPath('storage/logs/app.log'),
    $app->get(RequestContext::class)
));

$app->singleton(Database::class, static fn (Application $app): Database => new Database(
    $app->get(Config::class),
    $app->get(Logger::class)
));

$app->singleton(Session::class, static fn (Application $app): Session => new Session(
    $app->get(Config::class)
));

$app->singleton(Flash::class, static fn (Application $app): Flash => new Flash($app->get(Session::class)));

$app->singleton(Csrf::class, static fn (Application $app): Csrf => new Csrf($app->get(Session::class)));

$app->singleton(View::class, static fn (Application $app): View => new View(
    $app->rootPath('app/Views'),
    $app->get(Session::class),
    $app->get(Csrf::class),
    $app->get(Auth::class),
    $app->get(Config::class),
    $app->get(NotificationRepository::class)
));

$app->singleton(Response::class, static fn (Application $app): Response => new Response(
    $app->get(View::class),
    $app->get(Flash::class)
));

$app->singleton(HttpEmitter::class, static fn (): HttpEmitter => new HttpEmitter());

$app->singleton(Validator::class, static fn (): Validator => new Validator());

$app->singleton(UserRepository::class, static fn (Application $app): UserRepository => new UserRepository($app->get(Database::class)));
$app->singleton(RoleRepository::class, static fn (Application $app): RoleRepository => new RoleRepository($app->get(Database::class)));
$app->singleton(StudentRepository::class, static fn (Application $app): StudentRepository => new StudentRepository($app->get(Database::class)));
$app->singleton(AcademicRecordRepository::class, static fn (Application $app): AcademicRecordRepository => new AcademicRecordRepository($app->get(Database::class)));
$app->singleton(AuditLogRepository::class, static fn (Application $app): AuditLogRepository => new AuditLogRepository($app->get(Database::class)));
$app->singleton(NotificationRepository::class, static fn (Application $app): NotificationRepository => new NotificationRepository($app->get(Database::class)));
$app->singleton(RequestRepository::class, static fn (Application $app): RequestRepository => new RequestRepository($app->get(Database::class)));

$app->singleton(Auth::class, static fn (Application $app): Auth => new Auth(
    $app->get(Session::class),
    $app->get(UserRepository::class),
    $app->get(RoleRepository::class)
));

$app->singleton(AuditService::class, static fn (Application $app): AuditService => new AuditService(
    $app->get(AuditLogRepository::class),
    $app->get(Auth::class)
));

$app->singleton(FileStorageService::class, static fn (Application $app): FileStorageService => new FileStorageService(
    $app->rootPath('storage/app/private/uploads'),
    $app->get(Logger::class)
));

$app->singleton(BackupService::class, static fn (Application $app): BackupService => new BackupService(
    $app->get(Database::class),
    $app->get(Config::class),
    $app->get(Logger::class),
    $app->rootPath(),
    $app->get(S3BackupRemoteStore::class)
));

$app->singleton(S3BackupRemoteStore::class, static fn (Application $app): S3BackupRemoteStore => new S3BackupRemoteStore(
    $app->get(Config::class),
    static fn (string $method, string $url, array $headers, string $body): array => S3BackupRemoteStore::curlTransport(
        $method,
        $url,
        $headers,
        $body
    )
));

$app->singleton(HealthService::class, static fn (Application $app): HealthService => new HealthService(
    $app->get(Database::class),
    $app->get(Config::class),
    $app->get(RequestContext::class),
    $app->rootPath()
));

$app->singleton(BackupStatusService::class, static fn (Application $app): BackupStatusService => new BackupStatusService(
    $app->get(BackupService::class),
    $app->get(Config::class),
    $app->get(Logger::class),
    $app->get(RequestContext::class)
));

$app->singleton(StatusService::class, static fn (Application $app): StatusService => new StatusService(
    $app->get(StudentRepository::class),
    $app->get(AuditService::class),
    $app->get(Auth::class)
));

$app->singleton(EnrollmentStatusService::class, static fn (Application $app): EnrollmentStatusService => new EnrollmentStatusService(
    $app->get(StudentRepository::class),
    $app->get(AuditService::class),
    $app->get(Auth::class)
));

$app->singleton(StudentService::class, static fn (Application $app): StudentService => new StudentService(
    $app->get(StudentRepository::class),
    $app->get(FileStorageService::class),
    $app->get(StatusService::class),
    $app->get(AuditService::class)
));

$app->singleton(AccountService::class, static fn (Application $app): AccountService => new AccountService(
    $app->get(UserRepository::class),
    $app->get(FileStorageService::class),
    $app->get(AuditService::class)
));

$app->singleton(NotificationService::class, static fn (Application $app): NotificationService => new NotificationService(
    $app->get(NotificationRepository::class),
    $app->get(UserRepository::class),
    $app->get(RoleRepository::class),
    $app->get(Config::class),
    $app->get(Logger::class)
));

$app->singleton(OpsAlertService::class, static fn (Application $app): OpsAlertService => new OpsAlertService(
    $app->get(BackupStatusService::class),
    $app->get(NotificationService::class),
    $app->get(Logger::class),
    $app->get(RequestContext::class)
));

$app->singleton(SearchService::class, static fn (Application $app): SearchService => new SearchService(
    $app->get(StudentRepository::class),
    $app->get(AcademicRecordRepository::class)
));

$app->singleton(RequestService::class, static fn (Application $app): RequestService => new RequestService(
    $app->get(RequestRepository::class),
    $app->get(AuditService::class),
    $app->get(Auth::class),
    $app->get(NotificationService::class)
));

$app->singleton(DashboardService::class, static fn (Application $app): DashboardService => new DashboardService(
    $app->get(StudentRepository::class),
    $app->get(AuditLogRepository::class),
    $app->get(RequestRepository::class),
    $app->get(NotificationRepository::class),
    $app->get(UserRepository::class),
    $app->get(RoleRepository::class)
));

$app->singleton(ReportService::class, static fn (Application $app): ReportService => new ReportService(
    $app->get(StudentRepository::class),
    $app->get(RequestRepository::class),
    $app->get(AuditLogRepository::class),
    $app->get(NotificationRepository::class),
    $app->get(UserRepository::class),
    $app->get(RoleRepository::class)
));

$app->singleton(IdCardService::class, static fn (Application $app): IdCardService => new IdCardService(
    $app->get(StudentRepository::class),
    $app->rootPath('storage/app/public/id-cards'),
    string_value($app->get(Config::class)->get('app.url', 'http://127.0.0.1:8000'), 'http://127.0.0.1:8000')
));

$app->singleton(Router::class, static fn (Application $app): Router => (static function () use ($app): Router {
    $router = new Router($app);

    require $app->rootPath('config/routes.php');

    return $router;
})());

$app->singleton(HttpKernel::class, static fn (Application $app): HttpKernel => new HttpKernel($app));

$app->singleton(AuthController::class, static fn (Application $app): AuthController => new AuthController(
    $app->get(Response::class),
    $app->get(Auth::class),
    $app->get(Csrf::class),
    $app->get(Session::class)
));

$app->singleton(HealthController::class, static fn (Application $app): HealthController => new HealthController(
    $app->get(Response::class),
    $app->get(HealthService::class)
));

$app->singleton(DashboardController::class, static fn (Application $app): DashboardController => new DashboardController(
    $app->get(Response::class),
    $app->get(DashboardService::class),
    $app->get(Auth::class),
    $app->get(StudentRepository::class),
    $app->get(NotificationRepository::class)
));

$app->singleton(AccountController::class, static fn (Application $app): AccountController => new AccountController(
    $app->get(Response::class),
    $app->get(AccountService::class),
    $app->get(Validator::class),
    $app->get(Auth::class)
));

$app->singleton(StudentController::class, static fn (Application $app): StudentController => new StudentController(
    $app->get(Response::class),
    $app->get(StudentService::class),
    $app->get(StudentRepository::class),
    $app->get(SearchService::class),
    $app->get(Validator::class),
    $app->get(Auth::class)
));

$app->singleton(RecordController::class, static fn (Application $app): RecordController => new RecordController(
    $app->get(Response::class),
    $app->get(AcademicRecordRepository::class),
    $app->get(SearchService::class),
    $app->get(StudentRepository::class),
    $app->get(Auth::class)
));

$app->singleton(StatusController::class, static fn (Application $app): StatusController => new StatusController(
    $app->get(Response::class),
    $app->get(StudentRepository::class),
    $app->get(StatusService::class),
    $app->get(EnrollmentStatusService::class),
    $app->get(Auth::class)
));

$app->singleton(IdCardController::class, static fn (Application $app): IdCardController => new IdCardController(
    $app->get(Response::class),
    $app->get(IdCardService::class),
    $app->get(StudentRepository::class),
    $app->get(Auth::class)
));

$app->singleton(RequestController::class, static fn (Application $app): RequestController => new RequestController(
    $app->get(Response::class),
    $app->get(RequestRepository::class),
    $app->get(StudentRepository::class),
    $app->get(UserRepository::class),
    $app->get(RoleRepository::class),
    $app->get(FileStorageService::class),
    $app->get(RequestService::class),
    $app->get(Auth::class)
));

$app->singleton(NotificationController::class, static fn (Application $app): NotificationController => new NotificationController(
    $app->get(Response::class),
    $app->get(NotificationRepository::class),
    $app->get(Auth::class)
));

$app->singleton(AdminController::class, static fn (Application $app): AdminController => new AdminController(
    $app->get(Response::class),
    $app->get(UserRepository::class),
    $app->get(RoleRepository::class),
    $app->get(HealthService::class),
    $app->get(Logger::class),
    $app->get(BackupStatusService::class),
    $app->get(OpsAlertService::class),
    $app->get(AccountService::class),
    $app->get(Validator::class)
));

$app->singleton(ReportController::class, static fn (Application $app): ReportController => new ReportController(
    $app->get(Response::class),
    $app->get(ReportService::class),
    $app->get(StudentRepository::class)
));

date_default_timezone_set(string_value($app->get(Config::class)->get('app.timezone', 'UTC'), 'UTC'));

if ($app->get(Config::class)->get('app.debug', false)) {
    $whoops = new Whoops\Run();
    $whoops->pushHandler(new Whoops\Handler\PrettyPageHandler());
    $whoops->register();
}

return $app;
