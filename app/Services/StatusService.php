<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Repositories\StudentRepository;
use InvalidArgumentException;

final class StatusService
{
    public const ALLOWED_STATUSES = [
        'Pending',
        'Under Review',
        'Approved',
        'Rejected',
        'Completed',
    ];

    public function __construct(
        private readonly StudentRepository $students,
        private readonly AuditService $auditService,
        private readonly Auth $auth
    ) {
    }

    public function transition(int $studentId, string $status, string $remarks): void
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported status transition.');
        }

        $student = $this->students->find($studentId);
        if ($student === null) {
            throw new InvalidArgumentException('Student not found.');
        }

        $previousStatus = $student['latest_status'] ?? 'Pending';
        $createdAt = date('Y-m-d H:i:s');

        $this->students->addStatusHistory($studentId, $status, $remarks, $this->auth->id(), $createdAt);
        $this->auditService->log('student', $studentId, 'status_transition', [
            'status' => $previousStatus,
        ], [
            'status' => $status,
            'remarks' => $remarks,
            'assigned_user_id' => $this->auth->id(),
            'created_at' => $createdAt,
        ]);
    }
}
