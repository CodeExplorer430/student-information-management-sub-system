<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class NotificationRepository
{
    public function __construct(
        private readonly Database $database
    ) {
    }

    /**
     * @param array{user_id:int, entity_type:string, entity_id:int, title:string, message:string, is_read:int, created_at:string} $data
     */
    public function create(array $data): int
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO notifications (user_id, entity_type, entity_id, title, message, is_read, created_at)
             VALUES (:user_id, :entity_type, :entity_id, :title, :message, :is_read, :created_at)'
        );
        $statement->execute($data);

        return (int) $this->database->connection()->lastInsertId();
    }

    /**
     * @param array{notification_id:int, channel:string, recipient:string, status:string, error_message:string|null, delivered_at:string|null, created_at:string} $data
     */
    public function addDelivery(array $data): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO notification_deliveries (notification_id, channel, recipient, status, error_message, delivered_at, created_at)
             VALUES (:notification_id, :channel, :recipient, :status, :error_message, :delivered_at, :created_at)'
        );
        $statement->execute($data);
    }

    /**
     * @return list<NotificationRow>
     */
    public function forUser(int $userId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC, id DESC'
        );
        $statement->execute(['user_id' => $userId]);
        /** @var list<NotificationRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        foreach ($rows as &$row) {
            $row['deliveries'] = $this->deliveriesForNotification(map_int($row, 'id'));
        }
        unset($row);

        return $rows;
    }

    public function unreadCount(int $userId): int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function markAllRead(int $userId): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);
    }

    /**
     * @param array{search?: string, channel?: string, status?: string} $filters
     * @return list<NotificationDeliverySearchRow>
     */
    public function searchDeliveries(array $filters = []): array
    {
        $conditions = [];
        $params = [];
        $sql = 'SELECT notification_deliveries.*, notifications.title, notifications.user_id, users.name AS user_name
                FROM notification_deliveries
                INNER JOIN notifications ON notifications.id = notification_deliveries.notification_id
                INNER JOIN users ON users.id = notifications.user_id';

        if (!empty($filters['search'])) {
            $conditions[] = '(notifications.title LIKE :search OR users.name LIKE :search OR notification_deliveries.recipient LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['channel'])) {
            $conditions[] = 'notification_deliveries.channel = :channel';
            $params['channel'] = $filters['channel'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'notification_deliveries.status = :status';
            $params['status'] = $filters['status'];
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY notification_deliveries.created_at DESC, notification_deliveries.id DESC';
        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($params);

        /** @var list<NotificationDeliverySearchRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @return list<DeliverySummaryRow>
     */
    public function deliverySummary(): array
    {
        $statement = $this->database->query(
            'SELECT channel, status, COUNT(*) AS total
             FROM notification_deliveries
             GROUP BY channel, status
             ORDER BY channel ASC, status ASC'
        );

        /** @var list<DeliverySummaryRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @return list<NotificationDeliveryRow>
     */
    private function deliveriesForNotification(int $notificationId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM notification_deliveries WHERE notification_id = :notification_id ORDER BY id DESC'
        );
        $statement->execute(['notification_id' => $notificationId]);

        /** @var list<NotificationDeliveryRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }
}
