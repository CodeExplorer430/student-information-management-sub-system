<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Session;
use App\Repositories\StudentRepository;
use App\Services\EnrollmentStatusService;
use InvalidArgumentException;
use Tests\Support\IntegrationTestCase;

final class EnrollmentStatusServiceIntegrationTest extends IntegrationTestCase
{
    public function testTransitionUpdatesEnrollmentStatusAndHistory(): void
    {
        $session = $this->app->get(Session::class);
        $session->set('auth.user_id', 1);

        $service = $this->app->get(EnrollmentStatusService::class);
        $service->transition(2, 'Graduated', 'All program requirements completed.');

        $student = $this->app->get(StudentRepository::class)->find(2);
        self::assertNotNull($student);

        self::assertSame('Graduated', $student['enrollment_status']);
        self::assertSame('Graduated', $student['enrollment_status_history'][0]['status']);
        self::assertSame('All program requirements completed.', $student['enrollment_status_history'][0]['remarks']);
    }

    public function testTransitionRejectsMissingStudents(): void
    {
        $session = $this->app->get(Session::class);
        $session->set('auth.user_id', 1);

        $service = $this->app->get(EnrollmentStatusService::class);

        try {
            $service->transition(9999, 'Dropped', 'Missing student.');
            self::fail('Expected missing enrollment target to fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Student not found.', $exception->getMessage());
        }
    }
}
