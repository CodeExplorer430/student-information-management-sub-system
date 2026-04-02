<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Session;
use App\Repositories\RequestRepository;
use App\Services\RequestService;
use Tests\Support\IntegrationTestCase;

final class RequestRepositoryIntegrationTest extends IntegrationTestCase
{
    public function testSearchAndAggregationCoverQueueFilters(): void
    {
        $session = $this->app->get(Session::class);
        $session->set('auth.user_id', 3);

        $requestService = $this->app->get(RequestService::class);
        $requestId = $requestService->create(
            1,
            'Profile Update',
            'Adjust profile data',
            'Need to adjust address on file.',
            'Urgent',
            '2024-01-10'
        );

        $session->set('auth.user_id', 2);
        $requestService->transition($requestId, 'Under Review', 'Assigned for checking.', 2, 'Urgent', '2024-01-10', 'Pending validation');

        $repository = $this->app->get(RequestRepository::class);
        $rows = $repository->search([
            'search' => 'Adjust profile data',
            'status' => 'Under Review',
            'request_type' => 'Profile Update',
            'priority' => 'Urgent',
            'assigned_user_id' => 2,
            'student_id' => 1,
            'created_by_user_id' => 3,
            'department' => 'BSIT',
            'date_from' => '2024-01-01',
            'date_to' => '2026-12-31',
            'overdue_only' => '1',
        ]);

        self::assertNotEmpty($rows);
        self::assertSame($requestId, (int) $rows[0]['id']);
        self::assertNotEmpty($repository->history($requestId));
        self::assertSame('Under Review', $repository->find($requestId)['status'] ?? null);
        self::assertArrayHasKey('Under Review', $repository->countByStatus());
        self::assertArrayHasKey('Under Review', $repository->countByStatus(1));
        self::assertGreaterThanOrEqual(1, $repository->countOverdue());
        self::assertContains('Profile Update', $repository->requestTypes());
        self::assertNotEmpty($repository->recent(2, 1));
    }
}
