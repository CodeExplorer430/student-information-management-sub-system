<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\RequestRepository;
use App\Repositories\RoleRepository;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;

final class ReportService
{
    public function __construct(
        private readonly StudentRepository $students,
        private readonly RequestRepository $requests,
        private readonly AuditLogRepository $auditLogs,
        private readonly NotificationRepository $notifications,
        private readonly UserRepository $users,
        private readonly RoleRepository $roles
    ) {
    }

    /**
     * @param array{dataset?: string, search?: string, status?: string, request_type?: string, enrollment_status?: string, department?: string, date_from?: string, date_to?: string, channel?: string} $filters
     * @return array<string, mixed>
     */
    public function overview(array $filters = []): array
    {
        return [
            'studentCount' => $this->students->count(),
            'userCount' => $this->users->count(),
            'workflowStatusCounts' => $this->students->countByStatus(),
            'enrollmentStatusCounts' => $this->students->countByEnrollmentStatus(),
            'requestStatusCounts' => $this->requests->countByStatus(),
            'overdueRequestCount' => $this->requests->countOverdue(),
            'roleOverview' => $this->roles->allRoles(),
            'recentActivity' => $this->auditLogs->recent(10),
            'deliverySummary' => $this->notifications->deliverySummary(),
            'studentRows' => $this->students->search($filters),
            'requestRows' => $this->requests->search($filters),
            'auditRows' => $this->auditLogs->search($filters),
            'notificationRows' => $this->notifications->searchDeliveries($filters),
        ];
    }

    /**
     * @param array{dataset?: string, search?: string, status?: string, request_type?: string, enrollment_status?: string, department?: string, date_from?: string, date_to?: string, channel?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function exportRows(string $dataset, array $filters = []): array
    {
        return match ($dataset) {
            'students' => $this->students->search($filters),
            'requests' => $this->requests->search($filters),
            'audits' => $this->auditLogs->search($filters),
            'notifications' => $this->notifications->searchDeliveries($filters),
            default => [],
        };
    }
}
