<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\RequestRepository;
use App\Repositories\RoleRepository;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;
use Tests\Support\HttpIntegrationTestCase;

final class HttpControllerCoverageIntegrationTest extends HttpIntegrationTestCase
{
    public function testRequestStoreNoteAndAttachmentDownloadFlows(): void
    {
        $student = $this->studentForEmail('student@bcp.edu');
        $this->actingAs('student@bcp.edu');

        $store = $this->request('POST', '/requests', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/requests/create',
            'request_type' => 'Record Certification',
            'title' => 'Need transcript copy',
            'description' => 'Requesting a transcript copy for internship processing.',
            'priority' => 'Urgent',
            'due_at' => '2026-04-20',
        ]);

        self::assertSame(302, $store->status());
        $location = $store->headers()['Location'] ?? '';
        self::assertMatchesRegularExpression('#^/requests/\d+$#', (string) $location);
        $requestId = (int) basename((string) $location);

        $invalidStore = $this->request('POST', '/requests', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/requests/create',
            'request_type' => 'Not Valid',
            'title' => 'Broken request',
            'description' => 'Invalid type path.',
            'priority' => 'Normal',
            'due_at' => '',
        ]);
        $this->assertRedirect($invalidStore, '/requests/create');

        $tmpFile = tempnam(sys_get_temp_dir(), 'sims-note-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "hello attachment\n");

        $GLOBALS['__sims_test_move_uploaded_files'] = true;
        $this->actingAs('staff@bcp.edu');

        try {
            $note = $this->request('POST', '/requests/' . $requestId . '/notes', post: [
                '_csrf' => $this->csrfToken(),
                '_back' => '/requests/' . $requestId,
                'visibility' => 'student',
                'body' => 'Attached an update for the student.',
            ], files: [
                'attachment' => [
                    'name' => 'note.txt',
                    'tmp_name' => $tmpFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($tmpFile),
                ],
            ]);
        } finally {
            unset($GLOBALS['__sims_test_move_uploaded_files']);
            @unlink($tmpFile);
        }

        $this->assertRedirect($note, '/requests/' . $requestId);

        $request = $this->app->get(RequestRepository::class)->find($requestId);
        self::assertNotNull($request);
        self::assertNotEmpty($request['attachments']);

        $attachmentId = (int) ($request['attachments'][0]['id'] ?? 0);
        self::assertGreaterThan(0, $attachmentId);

        $this->actingAs('student@bcp.edu');
        $download = $this->request('GET', '/requests/attachments/' . $attachmentId . '/download');
        self::assertSame(200, $download->status());
        self::assertSame('text/plain', $download->headers()['Content-Type'] ?? null);
        self::assertStringContainsString('hello attachment', $download->body());

        $storagePath = dirname(__DIR__, 2) . '/storage/app/private/uploads/' . ($request['attachments'][0]['stored_name'] ?? '');
        @unlink($storagePath);

        $missingDownload = $this->request('GET', '/requests/attachments/' . $attachmentId . '/download');
        $this->assertRedirect($missingDownload, '/requests/' . $requestId);

        $otherStudent = $this->app->get(StudentRepository::class)->search(['search' => 'Student 2']);
        if ($otherStudent !== []) {
            $other = $otherStudent[0];
            $this->actingAs((string) ($other['email'] ?? 'student2@bcp.edu'));
            $blocked = $this->request('POST', '/requests/' . $requestId . '/notes', post: [
                '_csrf' => $this->csrfToken(),
                '_back' => '/requests/' . $requestId,
                'visibility' => 'student',
                'body' => 'Blocked note.',
            ]);
            $this->assertRedirect($blocked, '/requests');
        }
    }

    public function testStudentUpdateAndAdminRoleSyncFlows(): void
    {
        $this->actingAs('student@bcp.edu');

        $invalid = $this->request('POST', '/students/1/update', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/students/1/edit',
            'first_name' => '',
            'last_name' => '',
            'birthdate' => '',
            'program' => '',
            'year_level' => '',
            'email' => 'bad-email',
            'phone' => '',
            'address' => '',
            'guardian_name' => '',
            'guardian_contact' => '',
            'department' => '',
        ]);

        $this->assertHtml($invalid, 422);
        self::assertStringContainsString('Edit student profile', $invalid->body());
        self::assertStringContainsString('This field is required.', $invalid->body());

        $valid = $this->request('POST', '/students/1/update', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/students/1/edit',
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
        ]);

        $this->assertRedirect($valid, '/students/1');
        self::assertSame('09181234567', $this->app->get(StudentRepository::class)->find(1)['phone'] ?? null);

        $createBlocked = $this->request('GET', '/students/create');
        $this->assertRedirect($createBlocked, '/dashboard');

        $this->actingAs('admin@bcp.edu');

        $sync = $this->request('POST', '/admin/roles/faculty/permissions', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/admin/roles',
            'permissions' => ['records.view', 'dashboard.view_operations'],
        ]);

        $this->assertRedirect($sync, '/admin/roles');
        self::assertContains('records.view', $this->app->get(RoleRepository::class)->permissionsForRole('faculty'));
    }

    public function testRequestControllerCoversDeniedNotFoundAndVisibilityBranches(): void
    {
        $this->actingAs('admin@bcp.edu');

        $createMissingStudent = $this->request('GET', '/requests/create');
        $this->assertRedirect($createMissingStudent, '/dashboard');

        $storeMissingStudent = $this->request('POST', '/requests', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/requests/create',
            'request_type' => 'Profile Update',
            'title' => 'Missing student',
            'description' => 'Admin does not map to a student row.',
            'priority' => 'Normal',
        ]);
        $this->assertRedirect($storeMissingStudent, '/dashboard');

        $this->actingAs('student@bcp.edu');
        $stored = $this->request('POST', '/requests', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/requests/create',
            'request_type' => 'Profile Update',
            'title' => 'Visibility coverage',
            'description' => 'Covers note and attachment access branches.',
            'priority' => 'Normal',
        ]);
        $location = (string) ($stored->headers()['Location'] ?? '');
        self::assertMatchesRegularExpression('#^/requests/\d+$#', $location);
        $requestId = (int) basename($location);

        $studentVisibleNote = $this->request('POST', '/requests/' . $requestId . '/notes', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/requests/' . $requestId,
            'visibility' => 'internal',
            'body' => 'Student-submitted note should be forced to student visibility.',
        ]);
        $this->assertRedirect($studentVisibleNote, '/requests/' . $requestId);

        $request = $this->app->get(RequestRepository::class)->find($requestId);
        self::assertNotNull($request);
        self::assertSame('student', $request['notes'][0]['visibility'] ?? null);

        $this->actingAs('staff@bcp.edu');

        $missingShow = $this->request('GET', '/requests/9999');
        $this->assertHtml($missingShow, 404);
        self::assertStringContainsString('Resource not found', $missingShow->body());

        $missingNote = $this->request('POST', '/requests/9999/notes', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/requests/9999',
            'visibility' => 'student',
            'body' => 'Missing request.',
        ]);
        $this->assertHtml($missingNote, 404);

        $invalidNote = $this->request('POST', '/requests/' . $requestId . '/notes', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/requests/' . $requestId,
            'visibility' => 'student',
            'body' => '',
        ]);
        $this->assertRedirect($invalidNote, '/requests/' . $requestId);

        $tmpFile = tempnam(sys_get_temp_dir(), 'sims-internal-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "internal attachment\n");
        $GLOBALS['__sims_test_move_uploaded_files'] = true;

        try {
            $internalNote = $this->request('POST', '/requests/' . $requestId . '/notes', post: [
                '_csrf' => $this->csrfToken(),
                '_back' => '/requests/' . $requestId,
                'visibility' => 'internal',
                'body' => 'Internal queue note.',
            ], files: [
                'attachment' => [
                    'name' => 'internal.txt',
                    'tmp_name' => $tmpFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($tmpFile),
                ],
            ]);
        } finally {
            unset($GLOBALS['__sims_test_move_uploaded_files']);
        }

        $this->assertRedirect($internalNote, '/requests/' . $requestId);

        $request = $this->app->get(RequestRepository::class)->find($requestId);
        self::assertNotNull($request);
        $internalAttachmentId = (int) ($request['attachments'][0]['id'] ?? 0);
        self::assertGreaterThan(0, $internalAttachmentId);

        $missingAttachment = $this->request('GET', '/requests/attachments/9999/download');
        $this->assertHtml($missingAttachment, 404);

        $userRepository = $this->app->get(UserRepository::class);
        $otherStudentUserId = $userRepository->create([
            'name' => 'Coverage Student',
            'email' => 'coverage.student@bcp.edu',
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
            'roles' => ['student'],
            'department' => 'BSIT',
            'created_at' => '2026-03-31 10:00:00',
            'updated_at' => '2026-03-31 10:00:00',
        ]);
        self::assertGreaterThan(0, $otherStudentUserId);

        $this->actingAs('coverage.student@bcp.edu');

        $blockedShow = $this->request('GET', '/requests/' . $requestId);
        $this->assertRedirect($blockedShow, '/requests');

        $blockedNote = $this->request('POST', '/requests/' . $requestId . '/notes', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/requests/' . $requestId,
            'visibility' => 'student',
            'body' => 'Blocked note.',
        ]);
        $this->assertRedirect($blockedNote, '/requests');

        $blockedAttachment = $this->request('GET', '/requests/attachments/' . $internalAttachmentId . '/download');
        $this->assertRedirect($blockedAttachment, '/requests');

        $this->actingAs('faculty@bcp.edu');

        $deniedQueue = $this->request('GET', '/requests');
        $this->assertRedirect($deniedQueue, '/dashboard');

        $deniedShow = $this->request('GET', '/requests/' . $requestId);
        $this->assertRedirect($deniedShow, '/dashboard');
    }

    public function testStudentControllerCoversCreateNotFoundAndAccessBranches(): void
    {
        $this->actingAs('admin@bcp.edu');

        $missingShow = $this->request('GET', '/students/9999');
        $this->assertHtml($missingShow, 404);

        $missingEdit = $this->request('GET', '/students/9999/edit');
        $this->assertHtml($missingEdit, 404);

        $invalidCreate = $this->request('POST', '/students', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/students/create',
            'first_name' => '',
            'last_name' => '',
            'birthdate' => '',
            'program' => '',
            'year_level' => '',
            'email' => 'invalid',
            'phone' => '',
            'address' => '',
            'guardian_name' => '',
            'guardian_contact' => '',
            'department' => '',
        ]);
        $this->assertHtml($invalidCreate, 422);
        self::assertStringContainsString('Create a new student record', $invalidCreate->body());

        $badPhoto = tempnam(sys_get_temp_dir(), 'sims-student-photo-');
        self::assertNotFalse($badPhoto);
        file_put_contents($badPhoto, 'plain text');

        try {
            $photoFailure = $this->request('POST', '/students', post: [
                '_csrf' => $this->csrfToken(),
                '_back' => '/students/create',
                'first_name' => 'Image',
                'middle_name' => '',
                'last_name' => 'Failure',
                'birthdate' => '2005-03-14',
                'program' => 'BSIT',
                'year_level' => '2',
                'email' => 'image.failure@bcp.edu',
                'phone' => '09171234567',
                'address' => 'Malolos',
                'guardian_name' => 'Guardian',
                'guardian_contact' => '09170000000',
                'department' => 'BSIT',
            ], files: [
                'photo' => [
                    'name' => 'bad.txt',
                    'tmp_name' => $badPhoto,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($badPhoto),
                ],
            ]);
        } finally {
            @unlink($badPhoto);
        }

        $this->assertHtml($photoFailure, 422);
        self::assertStringContainsString('Only JPG, PNG, and WEBP images are allowed.', $photoFailure->body());

        $missingUpdate = $this->request('POST', '/students/9999/update', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/students/9999/edit',
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
        ]);
        $this->assertHtml($missingUpdate, 404);

        $userRepository = $this->app->get(UserRepository::class);
        $otherStudentUserId = $userRepository->create([
            'name' => 'Coverage Student',
            'email' => 'coverage.student@bcp.edu',
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
            'roles' => ['student'],
            'department' => 'BSIT',
            'created_at' => '2026-03-31 10:00:00',
            'updated_at' => '2026-03-31 10:00:00',
        ]);
        self::assertGreaterThan(0, $otherStudentUserId);

        $this->actingAs('coverage.student@bcp.edu');

        $blockedShow = $this->request('GET', '/students/1');
        $this->assertRedirect($blockedShow, '/students');

        $blockedEdit = $this->request('GET', '/students/1/edit');
        $this->assertRedirect($blockedEdit, '/students');

        $createBlocked = $this->request('GET', '/students/create');
        $this->assertRedirect($createBlocked, '/dashboard');
    }

    public function testIdCardControllerCoversGenerateDownloadPrintVerifyAndStudentOwnership(): void
    {
        $this->actingAs('admin@bcp.edu');

        $generateMissing = $this->request('POST', '/id-cards/generate', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/id-cards',
            'student_id' => '9999',
        ]);
        $this->assertRedirect($generateMissing, '/id-cards');

        $generate = $this->request('POST', '/id-cards/generate', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/id-cards',
            'student_id' => '1',
        ]);
        $this->assertRedirect($generate, '/id-cards/1/print');

        $print = $this->request('GET', '/id-cards/1/print');
        $this->assertHtml($print);
        self::assertStringContainsString('Aira Mendoza', $print->body());

        $verify = $this->request('GET', '/id-cards/1/verify');
        $this->assertHtml($verify);
        self::assertStringContainsString('BSI-2026-1001', $verify->body());

        $download = $this->request('GET', '/id-cards/1/download');
        self::assertSame(200, $download->status());
        self::assertSame('image/png', $download->headers()['Content-Type'] ?? null);

        $missingVerify = $this->request('GET', '/id-cards/9999/verify');
        $this->assertHtml($missingVerify, 404);

        $idCardFile = dirname(__DIR__, 2) . '/storage/app/public/id-cards/student-id-1.png';
        @unlink($idCardFile);

        $missingDownload = $this->request('GET', '/id-cards/1/download');
        $this->assertRedirect($missingDownload, '/id-cards');

        $missingPrint = $this->request('GET', '/id-cards/1/print');
        $this->assertRedirect($missingPrint, '/id-cards');

        $this->app->get(RoleRepository::class)->syncPermissions('student', [
            'dashboard.view_student',
            'students.view',
            'students.update',
            'requests.create',
            'requests.view_own',
            'statuses.view',
            'notifications.view',
            'id_cards.view',
        ]);
        $this->app->get(UserRepository::class)->create([
            'name' => 'Card Coverage Student',
            'email' => 'card.coverage@bcp.edu',
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
            'roles' => ['student'],
            'department' => 'BSIT',
            'created_at' => '2026-03-31 10:00:00',
            'updated_at' => '2026-03-31 10:00:00',
        ]);
        $this->actingAs('admin@bcp.edu');

        $regeneratedCard = $this->request('POST', '/id-cards/generate', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/id-cards',
            'student_id' => '1',
        ]);
        $this->assertRedirect($regeneratedCard, '/id-cards/1/print');

        $this->actingAs('card.coverage@bcp.edu');

        $blockedStudentDownload = $this->request('GET', '/id-cards/1/download');
        $this->assertRedirect($blockedStudentDownload, '/id-cards');
    }

    public function testAuthNotificationStatusAdminAndReportRoutesCoverRemainingPublicBranches(): void
    {
        $_SESSION['flash.messages'] = [
            ['type' => 'error', 'message' => 'Please sign in first.'],
            ['type' => 'success', 'message' => 'Keep this flash visible.'],
        ];

        $guestLogin = $this->request('GET', '/login');
        $this->assertHtml($guestLogin);
        self::assertStringNotContainsString('Please sign in first.</span>', $guestLogin->body());
        self::assertStringContainsString('Keep this flash visible.', $guestLogin->body());

        $invalidCredentials = $this->request('POST', '/login', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/login',
            'email' => 'admin@bcp.edu',
            'password' => 'wrong-password',
        ]);
        $this->assertRedirect($invalidCredentials, '/login');

        $this->actingAs('admin@bcp.edu');

        $alreadyAuthenticated = $this->request('GET', '/login');
        $this->assertRedirect($alreadyAuthenticated, '/dashboard');

        $_SESSION = [];

        $notificationsRedirect = $this->request('GET', '/notifications');
        $this->assertRedirect($notificationsRedirect, '/login');

        $markAllRedirect = $this->request('POST', '/notifications/mark-all-read', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/notifications',
        ]);
        $this->assertRedirect($markAllRedirect, '/login');

        $this->actingAs('admin@bcp.edu');

        $invalidDataset = $this->request('GET', '/reports', query: [
            'dataset' => 'invalid',
        ]);
        $this->assertHtml($invalidDataset);
        self::assertStringContainsString('Operational reporting and exports', $invalidDataset->body());

        $studentsExport = $this->request('GET', '/reports/export/students');
        self::assertSame('text/csv; charset=UTF-8', $studentsExport->headers()['Content-Type'] ?? null);
        self::assertStringContainsString('student_number,first_name,last_name,program,department,latest_status,enrollment_status', $studentsExport->body());

        $auditsExport = $this->request('GET', '/reports/export/audits');
        self::assertSame('text/csv; charset=UTF-8', $auditsExport->headers()['Content-Type'] ?? null);
        self::assertStringContainsString('actor_name,entity_type,entity_id,action,created_at', $auditsExport->body());

        $notificationsExport = $this->request('GET', '/reports/export/notifications');
        self::assertSame('text/csv; charset=UTF-8', $notificationsExport->headers()['Content-Type'] ?? null);
        self::assertStringContainsString('title,user_name,channel,recipient,status,created_at', $notificationsExport->body());

        $invalidRole = $this->request('POST', '/admin/users/1/role', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/admin/users',
            'roles' => ['admin', 'made-up-role'],
        ]);
        $this->assertRedirect($invalidRole, '/admin/users');

        $validRoleUpdate = $this->request('POST', '/admin/users/1/role', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/admin/users',
            'roles' => ['staff', 'admin'],
        ]);
        $this->assertRedirect($validRoleUpdate, '/admin/users');
        self::assertSame(['admin', 'staff'], $this->app->get(UserRepository::class)->find(1)['roles'] ?? null);

        $validTransition = $this->request('POST', '/statuses/1/transition', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/statuses/1',
            'status' => 'Approved',
            'remarks' => 'Coverage approval path.',
        ]);
        $this->assertRedirect($validTransition, '/statuses/1');
        self::assertSame('Approved', $this->app->get(StudentRepository::class)->find(1)['latest_status'] ?? null);

        $validEnrollment = $this->request('POST', '/statuses/1/enrollment-transition', post: [
            '_csrf' => $this->csrfToken(),
            '_back' => '/statuses/1',
            'enrollment_status' => 'On Leave',
            'remarks' => 'Coverage enrollment path.',
        ]);
        $this->assertRedirect($validEnrollment, '/statuses/1');
        self::assertSame('On Leave', $this->app->get(StudentRepository::class)->find(1)['enrollment_status'] ?? null);

        $missingStatus = $this->request('GET', '/statuses/9999');
        $this->assertHtml($missingStatus, 404);

        $studentPermissions = $this->app->get(RoleRepository::class)->permissionsForRole('student');
        $studentPermissions[] = 'records.view';
        $this->app->get(RoleRepository::class)->syncPermissions('student', array_values(array_unique($studentPermissions)));

        $otherStudentUserId = $this->app->get(UserRepository::class)->create([
            'name' => 'Coverage Student',
            'email' => 'coverage.status.records@bcp.edu',
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
            'roles' => ['student'],
            'department' => 'BSIT',
            'created_at' => '2026-03-31 10:00:00',
            'updated_at' => '2026-03-31 10:00:00',
        ]);
        self::assertGreaterThan(0, $otherStudentUserId);

        $otherStudentId = $this->app->get(StudentRepository::class)->create([
            'student_number' => 'BSI-2026-1997',
            'first_name' => 'Coverage',
            'middle_name' => '',
            'last_name' => 'Student',
            'birthdate' => '2005-02-12',
            'program' => 'BSIT',
            'year_level' => '2',
            'email' => 'coverage.status.records@bcp.edu',
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
        self::assertGreaterThan(0, $otherStudentId);

        $otherStudent = $this->app->get(StudentRepository::class)->find($otherStudentId);
        self::assertNotNull($otherStudent);

        $this->actingAs((string) ($otherStudent['email'] ?? ''));

        $studentStatuses = $this->request('GET', '/statuses');
        $this->assertHtml($studentStatuses);
        self::assertStringContainsString((string) ($otherStudent['student_number'] ?? ''), $studentStatuses->body());
        self::assertStringNotContainsString('BSI-2026-1001', $studentStatuses->body());

        $blockedStatus = $this->request('GET', '/statuses/1');
        $this->assertRedirect($blockedStatus, '/statuses');

        $studentRecords = $this->request('GET', '/records');
        $this->assertHtml($studentRecords);
        self::assertStringContainsString('Academic records viewer', $studentRecords->body());
        self::assertStringNotContainsString('BSI-2026-1001', $studentRecords->body());

        $blockedRecord = $this->request('GET', '/records/1');
        $this->assertRedirect($blockedRecord, '/records');

        $blockedRecordExport = $this->request('GET', '/records/1/export');
        $this->assertRedirect($blockedRecordExport, '/records');

        $this->actingAs('admin@bcp.edu');

        $missingRecord = $this->request('GET', '/records/9999');
        $this->assertHtml($missingRecord, 404);

        $missingRecordExport = $this->request('GET', '/records/9999/export');
        $this->assertHtml($missingRecordExport, 404);
    }
}
