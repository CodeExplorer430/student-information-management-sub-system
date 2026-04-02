<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Session;
use App\Repositories\StudentRepository;
use App\Services\StudentService;
use ReflectionMethod;
use RuntimeException;
use Tests\Support\IntegrationTestCase;

final class StudentServiceIntegrationTest extends IntegrationTestCase
{
    public function testCreateGeneratesDeterministicStudentProfileDefaultsAndAuditTrail(): void
    {
        $session = $this->app->get(Session::class);
        $session->set('auth.user_id', 1);

        $service = $this->app->get(StudentService::class);
        $studentId = $service->create([
            'first_name' => 'Nadia',
            'middle_name' => 'Lopez',
            'last_name' => 'Cruz',
            'birthdate' => '2005-07-01',
            'program' => 'BS Information Technology',
            'year_level' => '2',
            'email' => 'nadia.cruz@student.bcp.edu',
            'phone' => '09170000999',
            'address' => 'Plaridel, Bulacan',
            'guardian_name' => 'Liza Cruz',
            'guardian_contact' => '09170000123',
            'department' => 'BSIT',
        ], []);

        $student = $this->app->get(StudentRepository::class)->find($studentId);

        self::assertNotNull($student);
        self::assertSame(sprintf('BSI-%s-1002', date('Y')), $student['student_number']);
        self::assertSame('Active', $student['enrollment_status']);
        self::assertSame('Pending', $student['latest_status']);
        self::assertSame('Student profile created.', $student['status_history'][0]['remarks']);
        self::assertSame('Active', $student['enrollment_status_history'][0]['status']);
        self::assertContains('created', array_column($student['audit_logs'], 'action'));
    }

    public function testUpdatePersistsProfileChangesAndAuditEntry(): void
    {
        $session = $this->app->get(Session::class);
        $session->set('auth.user_id', 1);

        $service = $this->app->get(StudentService::class);
        $service->update(1, [
            'first_name' => 'Aira',
            'middle_name' => 'Lopez',
            'last_name' => 'Mendoza',
            'birthdate' => '2005-03-14',
            'program' => 'BS Information Technology',
            'year_level' => '3',
            'email' => 'student@bcp.edu',
            'phone' => '09998887777',
            'address' => 'Updated Malolos, Bulacan',
            'guardian_name' => 'Marites Mendoza',
            'guardian_contact' => '09170000011',
            'department' => 'BSIT',
        ], []);

        $student = $this->app->get(StudentRepository::class)->find(1);

        self::assertNotNull($student);
        self::assertSame('09998887777', $student['phone']);
        self::assertSame('Updated Malolos, Bulacan', $student['address']);
        self::assertContains('updated', array_column($student['audit_logs'], 'action'));
    }

    public function testUpdateRejectsMissingStudentAndAcceptsReplacementPhoto(): void
    {
        $session = $this->app->get(Session::class);
        $session->set('auth.user_id', 1);

        $service = $this->app->get(StudentService::class);

        try {
            $service->update(9999, [
                'first_name' => 'Missing',
                'middle_name' => '',
                'last_name' => 'Student',
                'birthdate' => '2005-03-14',
                'program' => 'BS Information Technology',
                'year_level' => '3',
                'email' => 'missing@bcp.edu',
                'phone' => '09181234567',
                'address' => 'Updated address',
                'guardian_name' => 'Guardian',
                'guardian_contact' => '09170000011',
                'department' => 'BSIT',
            ], []);
            self::fail('Expected missing student update to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Student not found.', $exception->getMessage());
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'sims-student-photo-');
        self::assertNotFalse($tmpFile);
        $image = imagecreatetruecolor(12, 12);
        self::assertInstanceOf(\GdImage::class, $image);
        imagepng($image, $tmpFile);
        imagedestroy($image);

        $GLOBALS['__sims_test_move_uploaded_files'] = true;

        try {
            $service->update(1, [
                'first_name' => 'Aira',
                'middle_name' => 'Lopez',
                'last_name' => 'Mendoza',
                'birthdate' => '2005-03-14',
                'program' => 'BS Information Technology',
                'year_level' => '3',
                'email' => 'student@bcp.edu',
                'phone' => '09181234567',
                'address' => 'Updated Malolos, Bulacan',
                'guardian_name' => 'Marites Mendoza',
                'guardian_contact' => '09170000011',
                'department' => 'BSIT',
            ], [
                'photo' => [
                    'name' => 'replacement.png',
                    'tmp_name' => $tmpFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($tmpFile),
                ],
            ]);
        } finally {
            unset($GLOBALS['__sims_test_move_uploaded_files']);
        }

        $student = $this->app->get(StudentRepository::class)->find(1);

        self::assertNotNull($student);
        self::assertStringStartsWith('student-photo-', (string) $student['photo_path']);
    }

    public function testCreateRejectsInvalidPhotoUploadMimeTypes(): void
    {
        $session = $this->app->get(Session::class);
        $session->set('auth.user_id', 1);

        $tmpFile = tempnam(sys_get_temp_dir(), 'sims-photo-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'plain text is not an image');

        $service = $this->app->get(StudentService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only JPG, PNG, and WEBP images are allowed.');

        try {
            $service->create([
                'first_name' => 'Nadia',
                'middle_name' => 'Lopez',
                'last_name' => 'Cruz',
                'birthdate' => '2005-07-01',
                'program' => 'BS Information Technology',
                'year_level' => '2',
                'email' => 'nadia.cruz.student2@bcp.edu',
                'phone' => '09170000999',
                'address' => 'Plaridel, Bulacan',
                'guardian_name' => 'Liza Cruz',
                'guardian_contact' => '09170000123',
                'department' => 'BSIT',
            ], [
                'photo' => [
                    'name' => 'not-an-image.txt',
                    'type' => 'text/plain',
                    'tmp_name' => $tmpFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($tmpFile),
                ],
            ]);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testRequiredStringRejectsMissingFields(): void
    {
        $service = $this->app->get(StudentService::class);
        $requiredString = new ReflectionMethod(StudentService::class, 'requiredString');
        $requiredString->setAccessible(true);

        self::assertSame('BSIT', $requiredString->invoke($service, ['department' => 'BSIT'], 'department'));

        try {
            $requiredString->invoke($service, ['department' => null], 'department');
            self::fail('Expected missing field access to fail.');
        } catch (\Throwable $exception) {
            self::assertStringContainsString('Student payload is missing [department].', $exception->getMessage());
        }
    }
}
