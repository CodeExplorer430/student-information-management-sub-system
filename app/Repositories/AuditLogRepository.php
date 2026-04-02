<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AuditLogRepository
{
    public function __construct(
        private readonly Database $database
    ) {
    }

    /**
     * @param array{user_id:int|null, entity_type:string, entity_id:int, action:string, old_values:string|null, new_values:string|null, created_at:string} $data
     */
    public function create(array $data): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO audit_logs (user_id, entity_type, entity_id, action, old_values, new_values, created_at)
             VALUES (:user_id, :entity_type, :entity_id, :action, :old_values, :new_values, :created_at)'
        );
        $statement->execute($data);
    }

    /**
     * @return list<AuditLogRow&array{actor_name?: string|null}>
     */
    public function recent(int $limit = 10): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT audit_logs.*, users.name AS actor_name
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.user_id
             ORDER BY audit_logs.created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<AuditLogRow&array{actor_name?: string|null}> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @param array{search?: string, entity_type?: string, date_from?: string, date_to?: string} $filters
     * @return list<AuditLogRow&array{actor_name?: string|null}>
     */
    public function search(array $filters = []): array
    {
        $conditions = [];
        $params = [];
        $sql = 'SELECT audit_logs.*, users.name AS actor_name
                FROM audit_logs
                LEFT JOIN users ON users.id = audit_logs.user_id';

        if (!empty($filters['search'])) {
            $conditions[] = '(users.name LIKE :search OR audit_logs.entity_type LIKE :search OR audit_logs.action LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['entity_type'])) {
            $conditions[] = 'audit_logs.entity_type = :entity_type';
            $params['entity_type'] = $filters['entity_type'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'audit_logs.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'audit_logs.created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY audit_logs.created_at DESC';

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($params);

        /** @var list<AuditLogRow&array{actor_name?: string|null}> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @return list<AuditLogRow&array{actor_name?: string|null}>
     */
    public function forEntity(string $entityType, int $entityId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT audit_logs.*, users.name AS actor_name
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.user_id
             WHERE entity_type = :entity_type AND entity_id = :entity_id
             ORDER BY created_at DESC'
        );
        $statement->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);

        /** @var list<AuditLogRow&array{actor_name?: string|null}> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }
}
