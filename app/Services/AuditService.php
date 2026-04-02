<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Repositories\AuditLogRepository;

final class AuditService
{
    public function __construct(
        private readonly AuditLogRepository $auditLogs,
        private readonly Auth $auth
    ) {
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function log(string $entityType, int $entityId, string $action, ?array $oldValues, ?array $newValues): void
    {
        $this->auditLogs->create([
            'user_id' => $this->auth->id(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'old_values' => $oldValues !== null ? json_encode($oldValues, JSON_THROW_ON_ERROR) : null,
            'new_values' => $newValues !== null ? json_encode($newValues, JSON_THROW_ON_ERROR) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
