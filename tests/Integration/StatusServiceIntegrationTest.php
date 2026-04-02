<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Session;
use App\Repositories\StudentRepository;
use App\Services\StatusService;
use InvalidArgumentException;
use Tests\Support\IntegrationTestCase;

final class StatusServiceIntegrationTest extends IntegrationTestCase
{
    public function testTransitionAppendsStatusHistory(): void
    {
        $session = $this->app->get(Session::class);
        $session->set('auth.user_id', 1);

        $service = $this->app->get(StatusService::class);
        $service->transition(2, 'Under Review', 'Documents received.');

        $student = $this->app->get(StudentRepository::class)->find(2);
        self::assertNotNull($student);

        self::assertSame('Under Review', $student['latest_status']);
        self::assertSame('Documents received.', $student['status_history'][0]['remarks']);
    }

    public function testTransitionRejectsMissingStudents(): void
    {
        $session = $this->app->get(Session::class);
        $session->set('auth.user_id', 1);

        $service = $this->app->get(StatusService::class);

        try {
            $service->transition(9999, 'Approved', 'Missing student.');
            self::fail('Expected missing-student status transition to fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Student not found.', $exception->getMessage());
        }
    }
}
