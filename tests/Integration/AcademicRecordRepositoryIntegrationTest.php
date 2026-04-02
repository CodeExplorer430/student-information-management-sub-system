<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Repositories\AcademicRecordRepository;
use Tests\Support\IntegrationTestCase;

final class AcademicRecordRepositoryIntegrationTest extends IntegrationTestCase
{
    public function testSearchSupportsStudentAndDepartmentFilters(): void
    {
        $repository = $this->app->get(AcademicRecordRepository::class);
        $database = $this->app->get(Database::class)->connection();

        $database->exec(<<<'SQL'
            INSERT INTO students (
                student_number, first_name, middle_name, last_name, birthdate, program, year_level,
                email, phone, address, guardian_name, guardian_contact, department, enrollment_status,
                photo_path, created_at, updated_at
            ) VALUES (
                'BSA-2026-1991', 'Filter', '', 'Student', '2004-05-01', 'BSA', '2',
                'filter.records@bcp.edu', '09170000099', 'Malolos', 'Guardian', '09170000098', 'BSA', 'Active',
                '', '2026-03-31 10:00:00', '2026-03-31 10:00:00'
            )
        SQL);

        $studentId = (int) $database->lastInsertId();
        $database->exec(sprintf(<<<'SQL'
            INSERT INTO academic_records (
                student_id, term_label, subject_code, subject_title, units, grade, created_at
            ) VALUES (
                %d, '2025-2026 1st Term', 'ACC101', 'Accounting Basics', 3, '1.50', '2026-03-31 10:00:00'
            )
        SQL, $studentId));

        $departmentResults = $repository->search(['department' => 'BSA']);
        $studentResults = $repository->search(['student' => 'BSA-2026-1991']);

        self::assertNotEmpty($departmentResults);
        self::assertNotEmpty($studentResults);
        self::assertContains('BSA', array_column($departmentResults, 'department'));
        self::assertContains('BSA-2026-1991', array_column($studentResults, 'student_number'));
    }
}
