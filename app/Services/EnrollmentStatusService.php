<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Repositories\StudentRepository;
use InvalidArgumentException;

final class EnrollmentStatusService
{
    public const ALLOWED_STATUSES = [
        'Active',
        'Dropped',
        'Graduated',
        'On Leave',
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
            throw new InvalidArgumentException('Unsupported enrollment status transition.');
        }

        $student = $this->students->find($studentId);
        if ($student === null) {
            throw new InvalidArgumentException('Student not found.');
        }

        $previousStatus = $student['enrollment_status'] ?? 'Active';
        $createdAt = date('Y-m-d H:i:s');

        $this->students->updateEnrollmentStatus($studentId, $status);
        $this->students->addEnrollmentStatusHistory($studentId, $status, $remarks, $this->auth->id(), $createdAt);
        $this->auditService->log('student', $studentId, 'enrollment_status_transition', [
            'enrollment_status' => $previousStatus,
        ], [
            'enrollment_status' => $status,
            'remarks' => $remarks,
            'assigned_user_id' => $this->auth->id(),
            'created_at' => $createdAt,
        ]);
    }
}
