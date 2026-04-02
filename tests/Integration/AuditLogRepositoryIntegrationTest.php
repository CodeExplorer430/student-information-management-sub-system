<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\AuditLogRepository;
use Tests\Support\IntegrationTestCase;

final class AuditLogRepositoryIntegrationTest extends IntegrationTestCase
{
    public function testForEntityReturnsStudentAuditEntries(): void
    {
        $repository = $this->app->get(AuditLogRepository::class);
        $repository->create([
            'user_id' => 1,
            'entity_type' => 'request',
            'entity_id' => 77,
            'action' => 'reviewed',
            'old_values' => null,
            'new_values' => '{"status":"Under Review"}',
            'created_at' => '2026-03-31 12:00:00',
        ]);

        $entries = $repository->forEntity('student', 1);
        $recent = $repository->recent(2);
        $search = $repository->search([
            'search' => 'reviewed',
            'entity_type' => 'request',
            'date_from' => '2026-03-31',
            'date_to' => '2026-03-31',
        ]);

        self::assertNotEmpty($entries);
        self::assertSame('student', $entries[0]['entity_type']);
        self::assertSame(1, (int) $entries[0]['entity_id']);
        self::assertNotEmpty($recent);
        self::assertSame('request', $search[0]['entity_type'] ?? null);
    }
}
