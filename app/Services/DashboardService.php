<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\RequestRepository;
use App\Repositories\RoleRepository;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;

final class DashboardService
{
    public function __construct(
        private readonly StudentRepository $students,
        private readonly AuditLogRepository $auditLogs,
        private readonly RequestRepository $requests,
        private readonly NotificationRepository $notifications,
        private readonly UserRepository $users,
        private readonly RoleRepository $roles
    ) {
    }

    /**
     * @param UserRow|null $user
     * @param StudentRow|null $studentRecord
     * @return array<string, mixed>
     */
    public function overview(?array $user = null, ?array $studentRecord = null): array
    {
        $role = (string) ($user['role'] ?? 'guest');
        $dashboardRole = $studentRecord !== null ? 'student' : $role;
        $requestCounts = $this->requests->countByStatus($studentRecord !== null ? (int) $studentRecord['id'] : null);
        $profileCompleteness = $studentRecord !== null ? $this->profileCompleteness($studentRecord) : null;

        return [
            'role' => $dashboardRole,
            'studentCount' => $this->students->count(),
            'userCount' => $this->users->count(),
            'workflowStatusCounts' => $this->students->countByStatus(),
            'enrollmentStatusCounts' => $this->students->countByEnrollmentStatus(),
            'recentStudents' => $this->students->recent(),
            'recentActivity' => $this->auditLogs->recent(),
            'requestStatusCounts' => $requestCounts,
            'overdueRequestCount' => $this->requests->countOverdue(),
            'notificationUnreadCount' => $user !== null ? $this->notifications->unreadCount((int) ($user['id'] ?? 0)) : 0,
            'notificationDeliverySummary' => $this->notifications->deliverySummary(),
            'recentRequests' => $this->requests->recent(8, $studentRecord !== null ? (int) $studentRecord['id'] : null),
            'roleOverview' => $this->roles->allRoles(),
            'studentRecord' => $studentRecord,
            'profileCompleteness' => $profileCompleteness,
            'idAvailable' => $studentRecord !== null && !empty($studentRecord['id_card_path']),
        ];
    }

    /**
     * @param StudentRow $studentRecord
     */
    private function profileCompleteness(array $studentRecord): int
    {
        $fields = [
            'first_name',
            'last_name',
            'birthdate',
            'program',
            'year_level',
            'email',
            'phone',
            'address',
            'guardian_name',
            'guardian_contact',
            'department',
        ];

        $filled = 0;
        foreach ($fields as $field) {
            if (trim((string) ($studentRecord[$field] ?? '')) !== '') {
                $filled++;
            }
        }

        return (int) round(($filled / count($fields)) * 100);
    }
}
