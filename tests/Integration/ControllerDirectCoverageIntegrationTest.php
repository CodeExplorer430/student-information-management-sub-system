<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\IdCardController;
use App\Controllers\NotificationController;
use App\Controllers\RecordController;
use App\Controllers\ReportController;
use App\Controllers\RequestController;
use App\Controllers\StudentController;
use App\Core\HttpResult;
use App\Core\HttpResultException;
use App\Repositories\RoleRepository;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;
use App\Services\RequestService;
use ReflectionMethod;
use Tests\Support\HttpIntegrationTestCase;

final class ControllerDirectCoverageIntegrationTest extends HttpIntegrationTestCase
{
    public function testRecordControllerCoversInternalAuthorizationOwnershipAndMissingBranches(): void
    {
        $controller = $this->app->get(RecordController::class);

        $_SESSION = [];

        $this->assertRedirect($this->captureResult(fn () => $controller->index()), '/dashboard');
        $this->assertRedirect($this->captureResult(fn () => $controller->show(1)), '/dashboard');
        $this->assertRedirect($this->captureResult(fn () => $controller->export(1)), '/dashboard');

        $studentPermissions = $this->app->get(RoleRepository::class)->permissionsForRole('student');
        $studentPermissions[] = 'records.view';
        $this->app->get(RoleRepository::class)->syncPermissions('student', array_values(array_unique($studentPermissions)));

        $otherStudent = $this->createStudentUser('coverage.records@bcp.edu', 'BSI-2026-1998');

        $this->actingAs('student@bcp.edu');

        $studentIndex = $this->captureResult(fn () => $controller->index());
        self::assertSame(200, $studentIndex->status());
        self::assertSame('text/html; charset=UTF-8', $studentIndex->headers()['Content-Type'] ?? null);
        self::assertStringContainsString('BSI-2026-1001', $studentIndex->body());
        self::assertStringNotContainsString((string) ($otherStudent['student_number'] ?? ''), $studentIndex->body());

        $this->actingAs((string) ($otherStudent['email'] ?? ''));

        $this->assertRedirect($this->captureResult(fn () => $controller->show(1)), '/records');
        $this->assertRedirect($this->captureResult(fn () => $controller->export(1)), '/records');

        $this->actingAs('admin@bcp.edu');

        $missingShow = $this->captureResult(fn () => $controller->show(9999));
        self::assertSame(404, $missingShow->status());
        self::assertSame('text/html; charset=UTF-8', $missingShow->headers()['Content-Type'] ?? null);

        $missingExport = $this->captureResult(fn () => $controller->export(9999));
        self::assertSame(404, $missingExport->status());
        self::assertSame('text/html; charset=UTF-8', $missingExport->headers()['Content-Type'] ?? null);
    }

    public function testNotificationAndAuthControllersCoverGuestAndAuthenticatedBranches(): void
    {
        $notificationController = $this->app->get(NotificationController::class);
        $authController = $this->app->get(AuthController::class);

        $this->assertRedirect($this->captureResult(fn () => $notificationController->index()), '/login');

        $_POST['_back'] = '/notifications';
        $this->assertRedirect($this->captureResult(fn () => $notificationController->markAllRead()), '/login');
        unset($_POST['_back']);

        $_SESSION['flash.messages'] = [
            ['type' => 'error', 'message' => 'Please sign in first.'],
            ['type' => 'success', 'message' => 'Keep this flash visible.'],
        ];

        $guestLogin = $this->captureResult(fn () => $authController->showLogin());
        self::assertSame(200, $guestLogin->status());
        self::assertSame('text/html; charset=UTF-8', $guestLogin->headers()['Content-Type'] ?? null);
        self::assertStringNotContainsString('Please sign in first.</span>', $guestLogin->body());
        self::assertStringContainsString('Keep this flash visible.', $guestLogin->body());

        $_POST = [
            '_csrf' => $this->csrfToken(),
            'email' => 'admin@bcp.edu',
            'password' => 'wrong-password',
        ];

        $this->assertRedirect($this->captureResult(fn () => $authController->login()), '/login');

        $this->actingAs('admin@bcp.edu');
        $this->assertRedirect($this->captureResult(fn () => $authController->showLogin()), '/dashboard');
    }

    public function testReportControllerToCsvCoversAllDatasetsAndFallback(): void
    {
        $controller = $this->app->get(ReportController::class);
        $toCsv = new ReflectionMethod(ReportController::class, 'toCsv');
        $toCsv->setAccessible(true);

        $studentsCsv = $toCsv->invoke($controller, 'students', [[
            'student_number' => 'BSI-2026-1001',
            'first_name' => 'Aira',
            'last_name' => 'Mendoza',
            'program' => 'BSIT',
            'department' => 'BSIT',
            'latest_status' => 'Pending',
            'enrollment_status' => 'Active',
        ]]);
        $auditsCsv = $toCsv->invoke($controller, 'audits', [[
            'actor_name' => 'Admin User',
            'entity_type' => 'student',
            'entity_id' => '1',
            'action' => 'updated',
            'created_at' => '2026-03-31 10:00:00',
        ]]);
        $notificationsCsv = $toCsv->invoke($controller, 'notifications', [[
            'title' => 'Queued notification',
            'user_name' => 'Student User',
            'channel' => 'email',
            'recipient' => 'student@bcp.edu',
            'status' => 'queued',
            'created_at' => '2026-03-31 10:00:00',
        ]]);
        $fallbackCsv = $toCsv->invoke($controller, 'unsupported', [[
            'title' => 'Ignored row',
        ]]);
        self::assertIsString($studentsCsv);
        self::assertIsString($auditsCsv);
        self::assertIsString($notificationsCsv);
        self::assertIsString($fallbackCsv);

        self::assertStringContainsString('student_number,first_name,last_name,program,department,latest_status,enrollment_status', $studentsCsv);
        self::assertStringContainsString('actor_name,entity_type,entity_id,action,created_at', $auditsCsv);
        self::assertStringContainsString('title,user_name,channel,recipient,status,created_at', $notificationsCsv);
        self::assertMatchesRegularExpression('/^\R$/', $fallbackCsv);
    }

    public function testIdCardControllerAuthorizationHelperCoversPermissionAndOwnershipBranches(): void
    {
        $controller = $this->app->get(IdCardController::class);
        $authorize = new ReflectionMethod(IdCardController::class, 'authorizeCardAccess');
        $authorize->setAccessible(true);

        $_SESSION = [];

        $student = $this->studentForEmail('student@bcp.edu');

        $this->assertRedirect(
            $this->captureResult(fn () => $authorize->invoke($controller, $student, 'download your own ID card')),
            '/dashboard'
        );

        $studentPermissions = $this->app->get(RoleRepository::class)->permissionsForRole('student');
        $studentPermissions[] = 'id_cards.view';
        $this->app->get(RoleRepository::class)->syncPermissions('student', array_values(array_unique($studentPermissions)));

        $otherStudent = $this->createStudentUser('coverage.idcards@bcp.edu', 'BSI-2026-1996');
        $this->actingAs((string) ($otherStudent['email'] ?? ''));

        $this->assertRedirect(
            $this->captureResult(fn () => $authorize->invoke($controller, $student, 'download your own ID card')),
            '/id-cards'
        );
    }

    public function testStudentControllerCreateAndUpdateCoverDirectAdminAndPhotoFailureBranches(): void
    {
        $controller = $this->app->get(StudentController::class);
        $this->actingAs('admin@bcp.edu');

        $create = $this->captureResult(fn () => $controller->create());
        self::assertSame(200, $create->status());
        self::assertStringContainsString('Create a new student record', $create->body());

        $tmpFile = tempnam(sys_get_temp_dir(), 'sims-update-photo-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'not an image');

        $_POST = [
            'first_name' => 'Aira',
            'middle_name' => 'Lopez',
            'last_name' => 'Mendoza',
            'birthdate' => '2005-03-14',
            'program' => 'BS Information Technology',
            'year_level' => '3',
            'email' => 'student@bcp.edu',
            'phone' => '09181234567',
            'address' => 'Updated address',
            'guardian_name' => 'Marites Mendoza',
            'guardian_contact' => '09170000011',
            'department' => 'BSIT',
        ];
        $_FILES['photo'] = [
            'name' => 'bad.txt',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        try {
            $update = $this->captureResult(fn () => $controller->update(1));
        } finally {
            @unlink($tmpFile);
            $_POST = [];
            $_FILES = [];
        }

        self::assertSame(422, $update->status());
        self::assertStringContainsString('Only JPG, PNG, and WEBP images are allowed.', $update->body());
    }

    public function testStudentControllerIndexCreateAndStoreCoverStudentFilterAndSuccessRedirects(): void
    {
        $controller = $this->app->get(StudentController::class);
        $otherStudent = $this->createStudentUser('coverage.student.filter@bcp.edu', 'BSI-2026-1994');

        $this->actingAs('student@bcp.edu');

        $index = $this->captureResult(fn () => $controller->index());
        self::assertSame(200, $index->status());
        self::assertStringContainsString('BSI-2026-1001', $index->body());
        self::assertStringNotContainsString((string) ($otherStudent['student_number'] ?? ''), $index->body());

        $createRedirect = $this->captureResult(fn () => $controller->create());
        $this->assertRedirect($createRedirect, '/students');

        $this->actingAs('admin@bcp.edu');
        $_POST = [
            'first_name' => 'Stored',
            'middle_name' => '',
            'last_name' => 'Student',
            'birthdate' => '2004-02-01',
            'program' => 'BS Information Technology',
            'year_level' => '1',
            'email' => 'stored.student@bcp.edu',
            'phone' => '09170000123',
            'address' => 'Malolos, Bulacan',
            'guardian_name' => 'Coverage Guardian',
            'guardian_contact' => '09170000456',
            'department' => 'BSIT',
        ];

        try {
            $store = $this->captureResult(fn () => $controller->store());
        } finally {
            $_POST = [];
        }

        self::assertSame(302, $store->status());
        self::assertStringStartsWith('/students/', (string) ($store->headers()['Location'] ?? ''));
    }

    public function testDashboardControllerStudentOverviewUsesStudentLookup(): void
    {
        $controller = $this->app->get(DashboardController::class);

        $this->actingAs('student@bcp.edu');

        $result = $this->captureResult(fn () => $controller->index());

        self::assertSame(200, $result->status());
        self::assertStringContainsString('BSI-2026-1001', $result->body());
    }

    public function testIdCardControllerIndexAndDownloadCoverStudentFilterAndMissingCardBranches(): void
    {
        $controller = $this->app->get(IdCardController::class);
        $otherStudent = $this->createStudentUser('coverage.idcards.filter@bcp.edu', 'BSI-2026-1993');

        $this->actingAs('student@bcp.edu');

        $index = $this->captureResult(fn () => $controller->index());
        self::assertSame(200, $index->status());
        self::assertStringContainsString('BSI-2026-1001', $index->body());
        self::assertStringNotContainsString((string) ($otherStudent['student_number'] ?? ''), $index->body());

        $missingDownload = $this->captureResult(fn () => $controller->download((int) $otherStudent['id']));
        $this->assertRedirect($missingDownload, '/id-cards');
    }

    public function testRequestControllerTransitionAndAccessHelperCoverDirectBranches(): void
    {
        $student = $this->studentForEmail('student@bcp.edu');
        $this->actingAs('student@bcp.edu');

        $requestId = $this->app->get(RequestService::class)->create(
            (int) $student['id'],
            'Profile Update',
            'Direct coverage request',
            'Covers request controller helper branches.'
        );

        $controller = $this->app->get(RequestController::class);
        $canAccess = new ReflectionMethod(RequestController::class, 'canAccessRequest');
        $canAccess->setAccessible(true);

        $request = $this->app->get(\App\Repositories\RequestRepository::class)->find($requestId);
        self::assertNotNull($request);

        self::assertTrue($canAccess->invoke($controller, $request));

        $otherStudent = $this->createStudentUser('coverage.requests@bcp.edu', 'BSI-2026-1995');
        $this->actingAs((string) ($otherStudent['email'] ?? ''));
        self::assertFalse($canAccess->invoke($controller, $request));

        $this->actingAs('admin@bcp.edu');
        self::assertTrue($canAccess->invoke($controller, $request));

        $_POST = [
            'status' => 'Completed',
            'remarks' => 'Resolved in direct controller coverage.',
            'assigned_user_id' => '1',
            'priority' => 'High',
            'due_at' => '2026-04-25',
            'resolution_summary' => 'Done',
        ];

        try {
            $transition = $this->captureResult(fn () => $controller->transition($requestId));
        } finally {
            $_POST = [];
        }

        $this->assertRedirect($transition, '/requests/' . $requestId);
    }

    private function captureResult(callable $callback): HttpResult
    {
        try {
            $callback();
        } catch (HttpResultException $exception) {
            return $exception->result();
        }

        self::fail('Expected the controller to throw an HttpResultException.');
    }

    /**
     * @return StudentRow
     */
    private function createStudentUser(string $email, string $studentNumber): array
    {
        $this->app->get(UserRepository::class)->create([
            'name' => 'Coverage Student',
            'email' => $email,
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
            'roles' => ['student'],
            'department' => 'BSIT',
            'created_at' => '2026-03-31 10:00:00',
            'updated_at' => '2026-03-31 10:00:00',
        ]);

        $studentId = $this->app->get(StudentRepository::class)->create([
            'student_number' => $studentNumber,
            'first_name' => 'Coverage',
            'middle_name' => '',
            'last_name' => 'Student',
            'birthdate' => '2005-02-12',
            'program' => 'BSIT',
            'year_level' => '2',
            'email' => $email,
            'phone' => '09171234567',
            'address' => 'Malolos',
            'guardian_name' => 'Coverage Guardian',
            'guardian_contact' => '09170000012',
            'department' => 'BSIT',
            'enrollment_status' => 'Active',
            'photo_path' => '',
            'created_at' => '2026-03-31 10:00:00',
            'updated_at' => '2026-03-31 10:00:00',
        ]);

        $student = $this->app->get(StudentRepository::class)->find($studentId);

        self::assertNotNull($student);

        return $student;
    }
}
