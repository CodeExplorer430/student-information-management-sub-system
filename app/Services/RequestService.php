<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Repositories\RequestRepository;
use InvalidArgumentException;

final class RequestService
{
    public const ALLOWED_STATUSES = [
        'Pending',
        'Under Review',
        'Approved',
        'Rejected',
        'Completed',
    ];

    public const REQUEST_TYPES = [
        'Profile Update',
        'Record Certification',
        'Enrollment Clarification',
        'Leave Status Review',
        'ID Issuance Support',
    ];

    public const PRIORITIES = [
        'Low',
        'Normal',
        'High',
        'Urgent',
    ];

    public const NOTE_VISIBILITIES = [
        'student',
        'internal',
    ];

    public function __construct(
        private readonly RequestRepository $requests,
        private readonly AuditService $audit,
        private readonly Auth $auth,
        private readonly NotificationService $notifications
    ) {
    }

    public function create(int $studentId, string $requestType, string $title, string $description, string $priority = 'Normal', ?string $dueAt = null): int
    {
        if (!in_array($requestType, self::REQUEST_TYPES, true)) {
            throw new InvalidArgumentException('Invalid request type.');
        }

        if (!in_array($priority, self::PRIORITIES, true)) {
            throw new InvalidArgumentException('Invalid request priority.');
        }

        if ($title === '' || $description === '') {
            throw new InvalidArgumentException('Title and description are required.');
        }

        $id = $this->requests->create([
            'student_id' => $studentId,
            'request_type' => $requestType,
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'due_at' => $dueAt !== null && $dueAt !== '' ? $dueAt . ' 17:00:00' : $this->defaultDueAt($priority),
            'status' => 'Pending',
            'assigned_user_id' => null,
            'created_by_user_id' => $this->auth->id(),
            'submitted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'resolved_at' => null,
            'resolution_summary' => null,
        ]);

        $this->requests->addHistory($id, 'Pending', 'Request submitted.', $this->auth->id());
        $this->audit->log('request', $id, 'created', null, ['status' => 'Pending', 'request_type' => $requestType]);
        $this->notifications->notifyPermissionRecipients(
            'requests.view_queue',
            'request',
            $id,
            'New request submitted',
            'A new ' . $requestType . ' request requires operational review.'
        );

        return $id;
    }

    public function transition(int $requestId, string $status, string $remarks, ?int $assignedUserId = null, ?string $priority = null, ?string $dueAt = null, ?string $resolutionSummary = null): void
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid request status.');
        }

        $request = $this->requests->find($requestId);
        if ($request === null) {
            throw new InvalidArgumentException('Request not found.');
        }

        $resolvedAt = in_array($status, ['Approved', 'Rejected', 'Completed'], true)
            ? date('Y-m-d H:i:s')
            : null;

        $this->requests->update($requestId, [
            'status' => $status,
            'priority' => $priority !== null && in_array($priority, self::PRIORITIES, true) ? $priority : (string) ($request['priority'] ?? 'Normal'),
            'due_at' => $dueAt !== null && $dueAt !== '' ? $dueAt . ' 17:00:00' : ($request['due_at'] ?? null),
            'assigned_user_id' => $assignedUserId,
            'updated_at' => date('Y-m-d H:i:s'),
            'resolved_at' => $resolvedAt,
            'resolution_summary' => $resolutionSummary !== null && $resolutionSummary !== '' ? $resolutionSummary : ($request['resolution_summary'] ?? null),
        ]);

        $this->requests->addHistory($requestId, $status, $remarks, $assignedUserId ?? $this->auth->id());
        $this->audit->log('request', $requestId, 'status_transition', ['status' => $request['status']], ['status' => $status, 'assigned_user_id' => $assignedUserId]);

        $recipients = [(int) ($request['created_by_user_id'] ?? 0)];
        if ($assignedUserId !== null) {
            $recipients[] = $assignedUserId;
        }
        $this->notifications->notifyUserIds(
            $recipients,
            'request',
            $requestId,
            'Request updated',
            'Request "' . (string) ($request['title'] ?? 'Request') . '" moved to ' . $status . '.'
        );
    }

    /**
     * @param array{stored_name:string, original_name:string, mime_type:string, file_size:int}|null $attachment
     */
    public function addNote(int $requestId, string $body, string $visibility, ?array $attachment = null): void
    {
        if ($body === '') {
            throw new InvalidArgumentException('Note body is required.');
        }

        if (!in_array($visibility, self::NOTE_VISIBILITIES, true)) {
            throw new InvalidArgumentException('Invalid note visibility.');
        }

        $request = $this->requests->find($requestId);
        if ($request === null) {
            throw new InvalidArgumentException('Request not found.');
        }

        $noteId = $this->requests->addNote([
            'request_id' => $requestId,
            'author_user_id' => $this->auth->id(),
            'visibility' => $visibility,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if ($attachment !== null) {
            $this->requests->addAttachment([
                'request_id' => $requestId,
                'note_id' => $noteId,
                'uploaded_by_user_id' => $this->auth->id(),
                'visibility' => $visibility,
                'original_name' => $attachment['original_name'],
                'stored_name' => $attachment['stored_name'],
                'mime_type' => $attachment['mime_type'],
                'file_size' => $attachment['file_size'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->audit->log('request', $requestId, 'note_added', null, ['visibility' => $visibility]);

        if ($visibility === 'student') {
            $this->notifications->notifyUserIds(
                [(int) ($request['created_by_user_id'] ?? 0)],
                'request',
                $requestId,
                'New request note',
                'A new update was added to "' . (string) ($request['title'] ?? 'Request') . '".'
            );
        }
    }

    private function defaultDueAt(string $priority): string
    {
        $days = match ($priority) {
            'Urgent' => 1,
            'High' => 2,
            'Low' => 7,
            default => 5,
        };

        return date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
    }
}
