<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Controllers\AccountController;
use App\Controllers\AdminController;
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

        $create = $this->captureResult(fn () => $controller->create());
        self::assertSame(200, $create->status());
        self::assertStringContainsString('Create a new student record', $create->body());

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

    public function testAccountControllerCoversGuestValidationExceptionAndSuccessBranches(): void
    {
        $controller = $this->app->get(AccountController::class);
        $exceptionMapper = new ReflectionMethod(AccountController::class, 'errorsForException');
        $exceptionMapper->setAccessible(true);

        $_SESSION = [];
        $this->assertRedirect($this->captureResult(fn () => $controller->show()), '/login');

        $this->actingAs('admin@bcp.edu');

        $show = $this->captureResult(fn () => $controller->show());
        self::assertSame(200, $show->status());
        self::assertStringContainsString('My account details', $show->body());

        $_POST = [
            'name' => '',
            'email' => 'not-an-email',
        ];

        try {
            $invalid = $this->captureResult(fn () => $controller->update());
        } finally {
            $_POST = [];
            $_FILES = [];
        }

        self::assertSame(422, $invalid->status());
        self::assertStringContainsString('This field is required.', $invalid->body());

        $_POST = [
            'name' => 'Elena Garcia',
            'email' => 'student@bcp.edu',
            'mobile_phone' => '09171234567',
            'department' => 'ICT',
        ];

        try {
            $duplicate = $this->captureResult(fn () => $controller->update());
        } finally {
            $_POST = [];
            $_FILES = [];
        }

        self::assertSame(422, $duplicate->status());
        self::assertStringContainsString('already assigned to another user', $duplicate->body());

        $badPhoto = tempnam(sys_get_temp_dir(), 'sims-account-photo-');
        self::assertNotFalse($badPhoto);
        file_put_contents($badPhoto, 'plain text');

        $_POST = [
            'name' => 'Elena Garcia',
            'email' => 'admin@bcp.edu',
            'mobile_phone' => '09171234567',
            'department' => 'ICT',
        ];
        $_FILES['photo'] = [
            'name' => 'bad.txt',
            'tmp_name' => $badPhoto,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($badPhoto),
        ];

        try {
            $photoFailure = $this->captureResult(fn () => $controller->update());
        } finally {
            @unlink($badPhoto);
            $_POST = [];
            $_FILES = [];
        }

        self::assertSame(422, $photoFailure->status());
        self::assertStringContainsString('Only JPG, PNG, and WEBP images are allowed.', $photoFailure->body());

        $_POST = [
            'name' => 'Elena Garcia Updated',
            'email' => 'admin@bcp.edu',
            'mobile_phone' => '09179998888',
            'department' => 'Operations',
        ];

        try {
            $updated = $this->captureResult(fn () => $controller->update());
        } finally {
            $_POST = [];
            $_FILES = [];
        }

        $this->assertRedirect($updated, '/account');

        $user = $this->app->get(UserRepository::class)->findByEmail('admin@bcp.edu');
        self::assertNotNull($user);
        self::assertSame('Elena Garcia Updated', $user['name']);
        self::assertSame('09179998888', $user['mobile_phone']);
        self::assertSame('Operations', $user['department']);

        /** @var array<string, array<int, string>> $genericErrors */
        $genericErrors = $exceptionMapper->invoke($controller, new \RuntimeException('Generic account issue.'));
        self::assertSame(['name' => ['Generic account issue.']], $genericErrors);
    }

    public function testAdminControllerCoversUserAccountEditAndResetBranches(): void
    {
        $controller = $this->app->get(AdminController::class);
        $exceptionMapper = new ReflectionMethod(AdminController::class, 'accountErrorsForException');
        $exceptionMapper->setAccessible(true);

        $this->actingAs('admin@bcp.edu');

        $missingEdit = $this->captureResult(fn () => $controller->editUser(9999));
        self::assertSame(404, $missingEdit->status());

        $edit = $this->captureResult(fn () => $controller->editUser(2));
        self::assertSame(200, $edit->status());
        self::assertStringContainsString('Admin password reset', $edit->body());

        $_POST = [
            'name' => '',
            'email' => 'bad-email',
        ];

        try {
            $invalidUpdate = $this->captureResult(fn () => $controller->updateUserAccount(2));
        } finally {
            $_POST = [];
            $_FILES = [];
        }

        self::assertSame(422, $invalidUpdate->status());
        self::assertStringContainsString('This field is required.', $invalidUpdate->body());

        $_POST = [
            'name' => 'Marco Villanueva',
            'email' => 'admin@bcp.edu',
            'mobile_phone' => '09170000000',
            'department' => 'Student Affairs',
        ];

        try {
            $duplicateUpdate = $this->captureResult(fn () => $controller->updateUserAccount(2));
        } finally {
            $_POST = [];
            $_FILES = [];
        }

        self::assertSame(422, $duplicateUpdate->status());
        self::assertStringContainsString('already assigned to another user', $duplicateUpdate->body());

        $badPhoto = tempnam(sys_get_temp_dir(), 'sims-admin-account-photo-');
        self::assertNotFalse($badPhoto);
        file_put_contents($badPhoto, 'plain text');

        $_POST = [
            'name' => 'Marco Villanueva',
            'email' => 'staff@bcp.edu',
            'mobile_phone' => '09170000000',
            'department' => 'Student Affairs',
        ];
        $_FILES['photo'] = [
            'name' => 'bad.txt',
            'tmp_name' => $badPhoto,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($badPhoto),
        ];

        try {
            $photoFailure = $this->captureResult(fn () => $controller->updateUserAccount(2));
        } finally {
            @unlink($badPhoto);
            $_POST = [];
            $_FILES = [];
        }

        self::assertSame(422, $photoFailure->status());
        self::assertStringContainsString('Only JPG, PNG, and WEBP images are allowed.', $photoFailure->body());

        $_POST = [
            'name' => 'Marco Villanueva Updated',
            'email' => 'staff@bcp.edu',
            'mobile_phone' => '09181112222',
            'department' => 'Operations',
        ];

        try {
            $validUpdate = $this->captureResult(fn () => $controller->updateUserAccount(2));
        } finally {
            $_POST = [];
            $_FILES = [];
        }

        $this->assertRedirect($validUpdate, '/admin/users/2/edit');

        $updatedUser = $this->app->get(UserRepository::class)->find(2);
        self::assertNotNull($updatedUser);
        self::assertSame('Marco Villanueva Updated', $updatedUser['name']);
        self::assertSame('09181112222', $updatedUser['mobile_phone']);
        self::assertSame('Operations', $updatedUser['department']);

        $missingUpdate = $this->captureResult(fn () => $controller->updateUserAccount(9999));
        self::assertSame(404, $missingUpdate->status());

        $_POST = [
            'password' => '',
            'password_confirmation' => '',
        ];

        try {
            $blankReset = $this->captureResult(fn () => $controller->resetUserPassword(2));
        } finally {
            $_POST = [];
        }

        self::assertSame(422, $blankReset->status());
        self::assertStringContainsString('Provide a password for the reset.', $blankReset->body());

        $_POST = [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'Mismatch123!',
        ];

        try {
            $mismatchReset = $this->captureResult(fn () => $controller->resetUserPassword(2));
        } finally {
            $_POST = [];
        }

        self::assertSame(422, $mismatchReset->status());
        self::assertStringContainsString('Password confirmation does not match.', $mismatchReset->body());

        $_POST = [
            'password' => 'AdminReset123!',
            'password_confirmation' => 'AdminReset123!',
        ];

        try {
            $reset = $this->captureResult(fn () => $controller->resetUserPassword(2));
        } finally {
            $_POST = [];
        }

        $this->assertRedirect($reset, '/admin/users/2/edit');

        $resetUser = $this->app->get(UserRepository::class)->find(2);
        self::assertNotNull($resetUser);
        self::assertTrue(password_verify('AdminReset123!', (string) $resetUser['password_hash']));

        $missingReset = $this->captureResult(fn () => $controller->resetUserPassword(9999));
        self::assertSame(404, $missingReset->status());

        /** @var array<string, array<int, string>> $genericErrors */
        $genericErrors = $exceptionMapper->invoke($controller, new \RuntimeException('Generic admin account issue.'));
        self::assertSame(['name' => ['Generic admin account issue.']], $genericErrors);
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
