<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\NotificationRepository;
use Tests\Support\IntegrationTestCase;

final class NotificationRepositoryIntegrationTest extends IntegrationTestCase
{
    public function testNotificationRepositoryCoversUnreadMarkReadSearchAndSummary(): void
    {
        $repository = $this->app->get(NotificationRepository::class);

        $notificationId = $repository->create([
            'user_id' => 3,
            'entity_type' => 'request',
            'entity_id' => 44,
            'title' => 'Coverage notification',
            'message' => 'Repository coverage message.',
            'is_read' => 0,
            'created_at' => '2026-03-31 12:00:00',
        ]);
        $repository->addDelivery([
            'notification_id' => $notificationId,
            'channel' => 'email',
            'recipient' => 'student@bcp.edu',
            'status' => 'sent',
            'error_message' => null,
            'delivered_at' => '2026-03-31 12:05:00',
            'created_at' => '2026-03-31 12:00:01',
        ]);

        $notifications = $repository->forUser(3);
        $filteredDeliveries = $repository->searchDeliveries([
            'search' => 'Coverage notification',
            'channel' => 'email',
            'status' => 'sent',
        ]);
        $summary = $repository->deliverySummary();

        self::assertNotEmpty($notifications);
        self::assertSame(2, $repository->unreadCount(3));
        self::assertNotEmpty($notifications[0]['deliveries']);
        self::assertNotEmpty($filteredDeliveries);
        self::assertContains('email', array_column($summary, 'channel'));

        $repository->markAllRead(3);

        self::assertSame(0, $repository->unreadCount(3));
    }
}
