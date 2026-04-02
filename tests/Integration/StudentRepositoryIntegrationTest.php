<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Repositories\StudentRepository;
use App\Services\IdCardService;
use ReflectionProperty;
use Tests\Support\IntegrationTestCase;

final class StudentRepositoryIntegrationTest extends IntegrationTestCase
{
    public function testSearchReturnsSeededStudentsAndLatestStatus(): void
    {
        $students = $this->app->get(StudentRepository::class)->search(['search' => 'Aira']);

        self::assertCount(1, $students);
        self::assertSame('Approved', $students[0]['latest_status']);
        self::assertSame('BSI-2026-1001', $students[0]['student_number']);
    }

    public function testSearchReturnsGeneratedIdCardMetadata(): void
    {
        $this->app->get(IdCardService::class)->generate(1, 5);

        $students = $this->app->get(StudentRepository::class)->search(['search' => 'Aira']);

        self::assertCount(1, $students);
        self::assertSame('student-id-1.png', $students[0]['id_card_path']);
        self::assertNotEmpty($students[0]['id_generated_at']);
    }

    public function testSearchCoversStatusEnrollmentDepartmentAndDateFilters(): void
    {
        $repository = $this->app->get(StudentRepository::class);

        $approved = $repository->search(['status' => 'Approved']);
        $active = $repository->search(['enrollment_status' => 'Active']);
        $department = $repository->search(['department' => 'BSIT']);
        $dateRange = $repository->search([
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ]);
        $empty = $repository->search([
            'search' => 'No matching student',
            'status' => 'Rejected',
            'enrollment_status' => 'Dropped',
            'department' => 'Nonexistent',
            'date_from' => '2030-01-01',
            'date_to' => '2030-12-31',
        ]);

        self::assertNotEmpty($approved);
        self::assertContains('Approved', array_column($approved, 'latest_status'));
        self::assertNotEmpty($active);
        self::assertContains('Active', array_column($active, 'enrollment_status'));
        self::assertNotEmpty($department);
        self::assertSame(['BSIT'], array_values(array_unique(array_column($department, 'department'))));
        self::assertNotEmpty($dateRange);
        self::assertSame([], $empty);
    }

    public function testRepositoryMethodsCoverCountsFindersAndHistoryUpdates(): void
    {
        $repository = $this->app->get(StudentRepository::class);

        self::assertSame(3, $repository->count());
        self::assertContains('BSIT', $repository->allDepartments());
        self::assertNull($repository->findByEmail('missing@bcp.edu'));
        self::assertNull($repository->find(9999));
        self::assertGreaterThanOrEqual(1002, $repository->nextSequenceForPrefixYear('BSI', (string) date('Y')));

        $student = $repository->find(1);
        self::assertNotNull($student);
        self::assertNotEmpty($student['status_history']);
        self::assertNotEmpty($student['enrollment_status_history']);
        self::assertNotEmpty($student['audit_logs']);

        $repository->update(1, [
            'first_name' => 'Aira',
            'middle_name' => 'Lopez',
            'last_name' => 'Mendoza',
            'birthdate' => '2005-03-14',
            'program' => 'BS Information Technology',
            'year_level' => '3',
            'email' => 'student@bcp.edu',
            'phone' => '09000000000',
            'address' => 'Updated from repository',
            'guardian_name' => 'Guardian',
            'guardian_contact' => '09170000011',
            'department' => 'BSIT',
            'photo_path' => '',
            'updated_at' => '2026-03-31 10:00:00',
        ]);
        $repository->addStatusHistory(1, 'Completed', 'Repository coverage status.', 2, '2026-03-31 11:00:00');
        $repository->updateEnrollmentStatus(1, 'Graduated');
        $repository->addEnrollmentStatusHistory(1, 'Graduated', 'Repository coverage enrollment.', 2, '2026-03-31 11:05:00');
        $repository->saveIdCard(1, 'coverage-card.png', '{"verify":"/id-cards/1/verify"}', 'BSI-2026-1001', 2);

        $updated = $repository->find(1);
        $recent = $repository->recent(2);
        $statusCounts = $repository->countByStatus();
        $enrollmentCounts = $repository->countByEnrollmentStatus();
        $latestCard = $repository->latestIdCard(1);

        self::assertNotNull($updated);
        self::assertSame('09000000000', $updated['phone']);
        self::assertSame('Completed', $updated['status_history'][0]['status'] ?? null);
        self::assertSame('Graduated', $updated['enrollment_status_history'][0]['status'] ?? null);
        self::assertNotEmpty($recent);
        self::assertArrayHasKey('Completed', $statusCounts);
        self::assertArrayHasKey('Graduated', $enrollmentCounts);
        self::assertNotNull($latestCard);
        self::assertSame('coverage-card.png', $latestCard['file_path']);
    }

    public function testNextSequenceSkipsNonStringStudentNumbers(): void
    {
        $database = new Database(
            new Config([
                'db' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
            ]),
            new Logger(tempnam(sys_get_temp_dir(), 'sims-student-seq-log-') ?: sys_get_temp_dir() . '/sims-student-seq.log')
        );
        $connection = new class ('sqlite::memory:') extends \PDO {
            private \PDO $helper;

            public function __construct(string $dsn)
            {
                parent::__construct($dsn);
                $this->helper = new \PDO('sqlite::memory:');
            }

            /**
             * @param array<int|string, mixed> $options
             */
            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                return $this->helper->prepare(
                    "SELECT 1234 AS student_number WHERE :pattern IS NOT NULL
                     UNION ALL
                     SELECT 'BSI-2026-1008' AS student_number WHERE :pattern IS NOT NULL"
                );
            }
        };

        $property = new ReflectionProperty(Database::class, 'connection');
        $property->setAccessible(true);
        $property->setValue($database, $connection);

        $repository = new StudentRepository($database);

        self::assertSame(1009, $repository->nextSequenceForPrefixYear('BSI', '2026'));
    }
}
