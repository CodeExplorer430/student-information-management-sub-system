<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;
use App\Services\DashboardService;
use App\Services\IdCardService;
use Tests\Support\IntegrationTestCase;

final class DashboardServiceIntegrationTest extends IntegrationTestCase
{
    public function testOverviewReturnsDashboardSummaries(): void
    {
        /** @var DashboardOverview $overview */
        $overview = $this->app->get(DashboardService::class)->overview();

        self::assertSame(3, $overview['studentCount']);
        self::assertSame(1, $overview['workflowStatusCounts']['Approved']);
        self::assertSame(2, $overview['enrollmentStatusCounts']['Active']);
        self::assertCount(3, $overview['recentStudents']);
        self::assertNotEmpty($overview['recentActivity']);
    }

    public function testOverviewForStudentIncludesProfileCompletenessAndIdAvailability(): void
    {
        $this->app->get(IdCardService::class)->generate(1, 1);

        $student = $this->app->get(StudentRepository::class)->find(1);
        $user = $this->app->get(UserRepository::class)->findByEmail('student@bcp.edu');

        self::assertNotNull($student);
        self::assertNotNull($user);

        /** @var DashboardOverview $overview */
        $overview = $this->app->get(DashboardService::class)->overview($user, $student);

        self::assertSame('student', $overview['role']);
        self::assertSame(100, $overview['profileCompleteness']);
        self::assertTrue($overview['idAvailable']);
        self::assertSame($student['id'], $overview['studentRecord']['id'] ?? null);
    }
}
