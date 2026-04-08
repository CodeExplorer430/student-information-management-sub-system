<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Application;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpKernel;
use App\Core\Logger;
use App\Core\RequestContext;
use App\Repositories\RequestRepository;
use App\Services\BackupService;
use App\Services\HealthService;
use App\Services\IdCardService;
use App\Services\RequestService;
use App\Support\DatabaseBuilder;
use RuntimeException;
use Tests\Support\HttpIntegrationTestCase;

final class HttpKernelIntegrationTest extends HttpIntegrationTestCase
{
    public function testGuestRedirectAndLoginLifecycleReturnExpectedResponses(): void
    {
        $guestDashboard = $this->request('GET', '/dashboard');

        $this->assertRedirect($guestDashboard, '/login');
        self::assertSame('Please sign in first.', $_SESSION['auth.login_notice'] ?? null);
        self::assertSame('DENY', $guestDashboard->headers()['X-Frame-Options'] ?? null);
        self::assertArrayHasKey('X-Request-Id', $guestDashboard->headers());

        $login = $this->request('GET', '/login');

        $this->assertHtml($login);
        self::assertArrayHasKey('X-Request-Id', $login->headers());
        self::assertStringContainsString('Secure access portal', $login->body());
        self::assertStringContainsString('Sign in to continue', $login->body());
        self::assertStringContainsString('Bestlink SIS', $login->body());
        self::assertStringContainsString('rel="manifest" href="/site.webmanifest"', $login->body());
        self::assertStringContainsString('href="/favicon-32x32.png"', $login->body());
        self::assertFileExists(dirname(__DIR__, 2) . '/public/favicon.ico');
        self::assertFileExists(dirname(__DIR__, 2) . '/public/site.webmanifest');

        $invalidLogin = $this->request('POST', '/login', post: [
            '_csrf' => 'invalid-token',
            '_back' => '/login',
            'email' => 'admin@bcp.edu',
            'password' => 'Password123!',
        ]);

        $this->assertRedirect($invalidLogin, '/login');

        $successfulLogin = $this->request('POST', '/login', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/login',
            'email' => 'admin@bcp.edu',
            'password' => 'Password123!',
        ]);

        $this->assertRedirect($successfulLogin, '/dashboard');
        self::assertIsInt($_SESSION['auth.user_id'] ?? null);

        $invalidLogout = $this->request('POST', '/logout', post: [
            '_csrf' => 'invalid-token',
            '_back' => '/dashboard',
        ]);

        $this->assertRedirect($invalidLogout, '/dashboard');
        self::assertIsInt($_SESSION['auth.user_id'] ?? null);

        $logout = $this->request('POST', '/logout', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/dashboard',
        ]);

        $this->assertRedirect($logout, '/login');
        self::assertArrayNotHasKey('auth.user_id', $_SESSION);
    }

    public function testAdminWorkspaceRoutesRenderPrimaryModules(): void
    {
        $admin = $this->actingAs('admin@bcp.edu');
        $student = $this->studentForEmail('student@bcp.edu');
        $requestId = $this->createRequestForStudent();
        $this->actingAs('admin@bcp.edu');

        $this->app->get(IdCardService::class)->generate((int) $student['id'], (int) $admin['id']);

        $cases = [
            ['path' => '/dashboard', 'query' => [], 'needle' => 'Governance and access oversight'],
            ['path' => '/account', 'query' => [], 'needle' => 'My account details'],
            ['path' => '/students', 'query' => ['search' => $student['student_number']], 'needle' => 'Student profile registration'],
            ['path' => '/students/create', 'query' => [], 'needle' => 'Create a new student record'],
            ['path' => '/students/' . $student['id'], 'query' => [], 'needle' => 'Profile change history'],
            ['path' => '/students/' . $student['id'] . '/edit', 'query' => [], 'needle' => 'Edit student profile'],
            ['path' => '/statuses', 'query' => ['search' => $student['student_number']], 'needle' => 'Status tracking board'],
            ['path' => '/statuses/' . $student['id'], 'query' => [], 'needle' => 'Student status tracking'],
            ['path' => '/id-cards', 'query' => ['search' => $student['student_number']], 'needle' => 'Student ID generation'],
            ['path' => '/records', 'query' => ['student' => $student['student_number']], 'needle' => 'Academic records viewer'],
            ['path' => '/records/' . $student['id'], 'query' => [], 'needle' => 'Academic record history'],
            ['path' => '/requests', 'query' => [], 'needle' => 'Request management queue'],
            ['path' => '/requests/' . $requestId, 'query' => [], 'needle' => 'Request history'],
            ['path' => '/notifications', 'query' => [], 'needle' => 'Notification center'],
            ['path' => '/admin/users', 'query' => [], 'needle' => 'User role assignment'],
            ['path' => '/admin/users/' . $admin['id'] . '/edit', 'query' => [], 'needle' => 'Admin password reset'],
            ['path' => '/admin/roles', 'query' => [], 'needle' => 'Save role permissions'],
            ['path' => '/admin/diagnostics', 'query' => [], 'needle' => 'Recent application events'],
            ['path' => '/reports', 'query' => ['dataset' => 'notifications'], 'needle' => 'Operational reporting and exports'],
        ];

        foreach ($cases as $case) {
            $response = $this->request('GET', $case['path'], query: $case['query']);

            $this->assertHtml($response);
            self::assertStringContainsString($case['needle'], $response->body());
        }
    }

    public function testPostFlowsAndDownloadsReturnExpectedResults(): void
    {
        $admin = $this->actingAs('admin@bcp.edu');
        $student = $this->studentForEmail('student@bcp.edu');
        $requestId = $this->createRequestForStudent();
        $this->actingAs('admin@bcp.edu');

        $invalidStudentCreate = $this->request('POST', '/students', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/students/create',
            'first_name' => '',
            'last_name' => '',
            'birthdate' => '',
            'program' => '',
            'year_level' => '',
            'email' => 'not-an-email',
            'phone' => '',
            'address' => '',
            'guardian_name' => '',
            'guardian_contact' => '',
            'department' => '',
        ]);

        $this->assertHtml($invalidStudentCreate, 422);
        self::assertStringContainsString('Create a new student record', $invalidStudentCreate->body());
        self::assertStringContainsString('This field is required.', $invalidStudentCreate->body());

        $invalidTransition = $this->request('POST', '/statuses/' . $student['id'] . '/transition', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/statuses/' . $student['id'],
            'status' => 'Invalid',
            'remarks' => 'Bad transition',
        ]);

        $this->assertRedirect($invalidTransition, '/statuses/' . $student['id']);

        $invalidEnrollment = $this->request('POST', '/statuses/' . $student['id'] . '/enrollment-transition', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/statuses/' . $student['id'],
            'enrollment_status' => 'Invalid',
            'remarks' => 'Bad enrollment transition',
        ]);

        $this->assertRedirect($invalidEnrollment, '/statuses/' . $student['id']);

        $invalidUserRole = $this->request('POST', '/admin/users/' . $admin['id'] . '/role', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/admin/users',
            'role' => '',
        ]);

        $this->assertRedirect($invalidUserRole, '/admin/users');

        $generateCard = $this->request('POST', '/id-cards/generate', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/id-cards',
            'student_id' => $student['id'],
        ]);

        $this->assertRedirect($generateCard, '/id-cards/' . $student['id'] . '/print');

        $preview = $this->request('GET', '/id-cards/' . $student['id'] . '/print');
        $this->assertHtml($preview);
        self::assertStringContainsString('Student ID preview', $preview->body());

        $verify = $this->request('GET', '/id-cards/' . $student['id'] . '/verify');
        $this->assertHtml($verify);
        self::assertStringContainsString('Verified Bestlink College student record', $verify->body());

        $download = $this->request('GET', '/id-cards/' . $student['id'] . '/download');
        self::assertSame(200, $download->status());
        self::assertSame('image/png', $download->headers()['Content-Type'] ?? null);
        self::assertSame('attachment; filename="student-id-' . $student['id'] . '.png"', $download->headers()['Content-Disposition'] ?? null);
        self::assertNotSame('', $download->body());

        $statement = $this->app->get(Database::class)->connection()->prepare(
            'UPDATE academic_records
             SET subject_title = :subject_title
             WHERE id = (
                 SELECT id FROM academic_records WHERE student_id = :student_id ORDER BY id ASC LIMIT 1
             )'
        );
        $statement->execute([
            'subject_title' => '=SUM(1,1)',
            'student_id' => $student['id'],
        ]);

        $recordsExport = $this->request('GET', '/records/' . $student['id'] . '/export');
        self::assertSame('text/csv; charset=UTF-8', $recordsExport->headers()['Content-Type'] ?? null);
        self::assertStringContainsString('term_label,subject_code,subject_title,units,grade', $recordsExport->body());
        self::assertStringContainsString("'=SUM(1,1)", $recordsExport->body());

        $reportExport = $this->request('GET', '/reports/export/requests', query: ['search' => 'Profile']);
        self::assertSame('text/csv; charset=UTF-8', $reportExport->headers()['Content-Type'] ?? null);
        self::assertStringContainsString('title,request_type,student_number,status,assigned_name,submitted_at', $reportExport->body());

        $unsupportedReport = $this->request('GET', '/reports/export/invalid');
        $this->assertRedirect($unsupportedReport, '/reports');

        $requestUpdate = $this->request('POST', '/requests/' . $requestId . '/transition', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/requests/' . $requestId,
            'status' => 'Under Review',
            'remarks' => 'Routing through registrar queue.',
            'assigned_user_id' => (string) $admin['id'],
            'priority' => 'High',
            'due_at' => '2026-04-15',
            'resolution_summary' => '',
        ]);

        $this->assertRedirect($requestUpdate, '/requests/' . $requestId);

        $updatedRequest = $this->app->get(RequestRepository::class)->find($requestId);

        self::assertNotNull($updatedRequest);
        self::assertSame('Under Review', $updatedRequest['status']);
    }

    public function testStudentScopeAndNotificationFlowsAreEnforced(): void
    {
        $student = $this->studentForEmail('student@bcp.edu');
        $otherStudent = $this->anotherStudent((int) $student['id']);
        $requestId = $this->createRequestForStudent();

        $this->actingAs('staff@bcp.edu');
        $this->app->get(RequestService::class)->addNote($requestId, 'Queue review update for the student.', 'student');

        $this->actingAs('student@bcp.edu');

        $requestCreate = $this->request('GET', '/requests/create');
        $this->assertHtml($requestCreate);
        self::assertStringContainsString('Submit a request', $requestCreate->body());

        $ownProfile = $this->request('GET', '/students/' . $student['id']);
        $this->assertHtml($ownProfile);
        self::assertStringContainsString('Student profile', $ownProfile->body());

        $blockedProfile = $this->request('GET', '/students/' . $otherStudent['id']);
        $this->assertRedirect($blockedProfile, '/students');

        $studentRequests = $this->request('GET', '/requests');
        $this->assertHtml($studentRequests);
        self::assertStringContainsString('My requests', $studentRequests->body());

        $notifications = $this->request('GET', '/notifications');
        $this->assertHtml($notifications);
        self::assertStringContainsString('Notification center', $notifications->body());
        self::assertStringContainsString('A new update was added to', $notifications->body());

        $markAllRead = $this->request('POST', '/notifications/mark-all-read', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/notifications',
        ], server: [
            'HTTP_REFERER' => '/notifications',
        ]);

        $this->assertRedirect($markAllRead, '/notifications');

        $markedNotifications = $this->request('GET', '/notifications');
        $this->assertHtml($markedNotifications);
        self::assertStringNotContainsString('badge bg-primary-lt ms-2">New</span>', $markedNotifications->body());
    }

    public function testEmptyStateAndMissingRoutePagesRenderExpectedContent(): void
    {
        $this->actingAs('admin@bcp.edu');

        $emptyStudents = $this->request('GET', '/students', query: [
            'search' => 'definitely-no-student-match',
        ]);

        $this->assertHtml($emptyStudents);
        self::assertStringContainsString('No matching student profiles', $emptyStudents->body());

        $missing = $this->request('GET', '/missing-route');

        $this->assertHtml($missing, 404);
        self::assertStringContainsString('Resource not found', $missing->body());
        self::assertStringContainsString('Back to dashboard', $missing->body());
    }

    public function testAdminDiagnosticsRendersEmptyLogState(): void
    {
        $this->actingAs('admin@bcp.edu');
        file_put_contents($this->app->rootPath('storage/logs/app.log'), '');

        $response = $this->request('GET', '/admin/diagnostics');

        $this->assertHtml($response);
        self::assertStringContainsString('Backup health', $response->body());
        self::assertStringContainsString('Operational alerts', $response->body());
        self::assertStringContainsString('backup.local.stale', $response->body());
        self::assertStringContainsString('local_backup_recency', $response->body());
        self::assertStringContainsString('No application events recorded yet.', $response->body());
    }

    public function testAdminDiagnosticsRendersEmptyOperationalAlertStateWhenChecksPass(): void
    {
        $this->actingAs('admin@bcp.edu');
        $backups = $this->app->get(BackupService::class);
        $manifest = $backups->create();
        $backups->verify($manifest['id']);
        $backups->drill($manifest['id']);

        $response = $this->request('GET', '/admin/diagnostics');

        $this->assertHtml($response);
        self::assertStringContainsString('Operational alerts', $response->body());
        self::assertStringContainsString('No active operational alerts.', $response->body());
    }

    public function testHealthEndpointsReturnMachineReadableStatus(): void
    {
        $live = $this->request('GET', '/health/live');
        self::assertSame(200, $live->status());
        self::assertSame('application/json; charset=UTF-8', $live->headers()['Content-Type'] ?? null);
        self::assertArrayHasKey('X-Request-Id', $live->headers());
        $livePayload = json_decode($live->body(), true);
        self::assertIsArray($livePayload);
        self::assertSame('pass', $livePayload['status'] ?? null);
        self::assertSame($live->headers()['X-Request-Id'] ?? null, $livePayload['request_id'] ?? null);

        $ready = $this->request('GET', '/health/ready');
        self::assertSame(200, $ready->status());
        $readyPayload = json_decode($ready->body(), true);
        self::assertIsArray($readyPayload);
        self::assertSame('pass', $readyPayload['status'] ?? null);
        self::assertNotEmpty($readyPayload['checks'] ?? []);
    }

    public function testHttpKernelReturnsSetupResponsesForMissingSchemaAndUnavailableDatabase(): void
    {
        $schemaKernel = $this->buildSetupKernel(new class () {
            public function connection(): \PDO
            {
                $database = new \PDO('sqlite::memory:');
                $database->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $database->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT)');

                return $database;
            }
        }, [
            'db' => [
                'database' => 'test.sqlite',
            ],
        ]);

        $schemaResponse = $schemaKernel->handle('GET', '/dashboard');

        self::assertSame(503, $schemaResponse->status());
        self::assertStringContainsString('Database migration required', $schemaResponse->body());
        self::assertStringContainsString('Run: composer migrate', $schemaResponse->body());
        self::assertArrayHasKey('X-Request-Id', $schemaResponse->headers());

        $columnKernel = $this->buildSetupKernel(new class () {
            public function connection(): \PDO
            {
                $database = new \PDO('sqlite::memory:');
                $database->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                foreach (DatabaseBuilder::requiredTables() as $table) {
                    $database->exec(sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY)', $table));
                }

                return $database;
            }
        }, [
            'db' => [
                'database' => 'column-drift.sqlite',
            ],
        ]);

        $columnResponse = $columnKernel->handle('GET', '/admin/users');

        self::assertSame(503, $columnResponse->status());
        self::assertStringContainsString('Database migration required', $columnResponse->body());
        self::assertStringContainsString('Missing columns: users.mobile_phone, users.photo_path', $columnResponse->body());
        self::assertStringContainsString('php bin/console env:check', $columnResponse->body());
        self::assertArrayHasKey('X-Request-Id', $columnResponse->headers());

        $failureKernel = $this->buildSetupKernel(new class () {
            public function connection(): never
            {
                throw new RuntimeException('simulated connection failure');
            }
        }, [
            'db' => [
                'database' => 'broken.sqlite',
            ],
        ]);

        $failureResponse = $failureKernel->handle('GET', '/dashboard');

        self::assertSame(503, $failureResponse->status());
        self::assertStringContainsString('Database is unavailable', $failureResponse->body());
        self::assertStringContainsString('simulated connection failure', $failureResponse->body());
        self::assertArrayHasKey('X-Request-Id', $failureResponse->headers());
    }

    private function createRequestForStudent(): int
    {
        $student = $this->studentForEmail('student@bcp.edu');
        $this->actingAs('student@bcp.edu');

        return $this->app->get(RequestService::class)->create(
            (int) $student['id'],
            'Profile Update',
            'Correct mobile contact',
            'Student needs to update the recorded mobile contact for urgent notices.'
        );
    }

    /**
     * @return StudentRow
     */
    private function anotherStudent(int $excludeId): array
    {
        $students = $this->app->get(\App\Repositories\StudentRepository::class)->search();

        foreach ($students as $student) {
            if ((int) $student['id'] !== $excludeId) {
                return $student;
            }
        }

        self::fail('Expected at least one additional seeded student.');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildSetupKernel(object $database, array $config): HttpKernel
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->singleton(Config::class, static fn (): Config => new Config(array_merge([
            'app' => [
                'url' => 'http://127.0.0.1:8000',
                'version' => 'test-build',
            ],
            'db' => [
                'database' => '',
            ],
        ], $config)));
        $app->singleton(RequestContext::class, static fn (): RequestContext => new RequestContext());
        $app->singleton(Logger::class, static fn (Application $app): Logger => new Logger(
            $app->rootPath('storage/logs/test-http.log'),
            $app->get(RequestContext::class)
        ));
        $app->singleton(Database::class, static fn () => $database);
        $app->singleton(HealthService::class, static fn (Application $app): HealthService => new HealthService(
            $app->get(Database::class),
            $app->get(Config::class),
            $app->get(RequestContext::class),
            $app->rootPath()
        ));

        return new HttpKernel($app);
    }
}
