<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class RequestRepository
{
    public function __construct(
        private readonly Database $database
    ) {
    }

    /**
     * @param array{search?: string, status?: string, request_type?: string, priority?: string, assigned_user_id?: int|string, student_id?: int|string, created_by_user_id?: int|string, department?: string, date_from?: string, date_to?: string, overdue_only?: string} $filters
     * @return list<RequestRow>
     */
    public function search(array $filters = []): array
    {
        $conditions = [];
        $params = [];
        $sql = 'SELECT student_requests.*,
                    students.student_number,
                    students.first_name,
                    students.last_name,
                    students.department,
                    students.email AS student_email,
                    assigned.name AS assigned_name,
                    created_by.name AS created_by_name
                FROM student_requests
                INNER JOIN students ON students.id = student_requests.student_id
                LEFT JOIN users assigned ON assigned.id = student_requests.assigned_user_id
                INNER JOIN users created_by ON created_by.id = student_requests.created_by_user_id';

        if (!empty($filters['search'])) {
            $conditions[] = '(students.student_number LIKE :search OR students.first_name LIKE :search OR students.last_name LIKE :search OR student_requests.title LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'student_requests.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['request_type'])) {
            $conditions[] = 'student_requests.request_type = :request_type';
            $params['request_type'] = $filters['request_type'];
        }

        if (!empty($filters['priority'])) {
            $conditions[] = 'student_requests.priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['assigned_user_id'])) {
            $conditions[] = 'student_requests.assigned_user_id = :assigned_user_id';
            $params['assigned_user_id'] = (int) $filters['assigned_user_id'];
        }

        if (!empty($filters['student_id'])) {
            $conditions[] = 'student_requests.student_id = :student_id';
            $params['student_id'] = (int) $filters['student_id'];
        }

        if (!empty($filters['created_by_user_id'])) {
            $conditions[] = 'student_requests.created_by_user_id = :created_by_user_id';
            $params['created_by_user_id'] = (int) $filters['created_by_user_id'];
        }

        if (!empty($filters['department'])) {
            $conditions[] = 'students.department = :department';
            $params['department'] = $filters['department'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'student_requests.submitted_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'student_requests.submitted_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if (($filters['overdue_only'] ?? '') === '1') {
            $conditions[] = 'student_requests.due_at IS NOT NULL AND student_requests.due_at < :overdue_now AND student_requests.status NOT IN ("Completed", "Rejected")';
            $params['overdue_now'] = date('Y-m-d H:i:s');
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY student_requests.updated_at DESC, student_requests.id DESC';

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($params);

        /** @var list<RequestRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @return RequestRow|null
     */
    public function find(int $id): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT student_requests.*,
                students.student_number,
                students.first_name,
                students.last_name,
                students.department,
                students.email AS student_email,
                assigned.name AS assigned_name,
                created_by.name AS created_by_name
             FROM student_requests
             INNER JOIN students ON students.id = student_requests.student_id
             LEFT JOIN users assigned ON assigned.id = student_requests.assigned_user_id
             INNER JOIN users created_by ON created_by.id = student_requests.created_by_user_id
             WHERE student_requests.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $request = $statement->fetch();

        if (!is_array($request)) {
            return null;
        }

        $request = map_value($request);
        $request['history'] = $this->history(map_int($request, 'id'));
        $request['notes'] = $this->notes(map_int($request, 'id'));
        $request['attachments'] = $this->attachments(map_int($request, 'id'));

        /** @var RequestRow $request */
        return $request;
    }

    /**
     * @param array{student_id:int, request_type:string, title:string, description:string, priority:string, due_at:string|null, status:string, assigned_user_id:int|null, created_by_user_id:int|null, submitted_at:string, updated_at:string, resolved_at:string|null, resolution_summary:string|null} $data
     */
    public function create(array $data): int
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO student_requests (
                student_id, request_type, title, description, priority, due_at, status, assigned_user_id, created_by_user_id, submitted_at, updated_at, resolved_at, resolution_summary
             ) VALUES (
                :student_id, :request_type, :title, :description, :priority, :due_at, :status, :assigned_user_id, :created_by_user_id, :submitted_at, :updated_at, :resolved_at, :resolution_summary
             )'
        );
        $statement->execute($data);

        return (int) $this->database->connection()->lastInsertId();
    }

    /**
     * @param array{status:string, priority:string, due_at:string|null, assigned_user_id:int|null, updated_at:string, resolved_at:string|null, resolution_summary:string|null, id?: int} $data
     */
    public function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $statement = $this->database->connection()->prepare(
            'UPDATE student_requests SET
                status = :status,
                priority = :priority,
                due_at = :due_at,
                assigned_user_id = :assigned_user_id,
                updated_at = :updated_at,
                resolved_at = :resolved_at,
                resolution_summary = :resolution_summary
             WHERE id = :id'
        );
        $statement->execute($data);
    }

    public function addHistory(int $requestId, string $status, string $remarks, ?int $assignedUserId): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO request_status_histories (request_id, status, remarks, assigned_user_id, created_at)
             VALUES (:request_id, :status, :remarks, :assigned_user_id, :created_at)'
        );
        $statement->execute([
            'request_id' => $requestId,
            'status' => $status,
            'remarks' => $remarks,
            'assigned_user_id' => $assignedUserId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<RequestHistoryRow>
     */
    public function history(int $requestId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT request_status_histories.*, users.name AS assigned_name
             FROM request_status_histories
             LEFT JOIN users ON users.id = request_status_histories.assigned_user_id
             WHERE request_id = :request_id
             ORDER BY id DESC'
        );
        $statement->execute(['request_id' => $requestId]);

        /** @var list<RequestHistoryRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(?int $studentId = null): array
    {
        $sql = 'SELECT status, COUNT(*) AS total FROM student_requests';
        $params = [];
        if ($studentId !== null) {
            $sql .= ' WHERE student_id = :student_id';
            $params['student_id'] = $studentId;
        }
        $sql .= ' GROUP BY status ORDER BY status ASC';

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($params);

        $counts = [];
        foreach (rows_value($statement->fetchAll(PDO::FETCH_ASSOC)) as $row) {
            $counts[map_string($row, 'status')] = map_int($row, 'total');
        }

        return $counts;
    }

    public function countOverdue(): int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM student_requests
             WHERE due_at IS NOT NULL AND due_at < :now AND status NOT IN ("Completed", "Rejected")'
        );
        $statement->execute(['now' => date('Y-m-d H:i:s')]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<string>
     */
    public function requestTypes(): array
    {
        $rows = $this->database->query('SELECT DISTINCT request_type FROM student_requests ORDER BY request_type ASC')
            ->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter($rows, static fn ($value): bool => is_string($value) && $value !== ''));
    }

    /**
     * @return list<RequestRow>
     */
    public function recent(int $limit = 8, ?int $studentId = null): array
    {
        $sql = 'SELECT student_requests.*,
                    students.student_number,
                    students.first_name,
                    students.last_name
                FROM student_requests
                INNER JOIN students ON students.id = student_requests.student_id';
        $params = [];

        if ($studentId !== null) {
            $sql .= ' WHERE student_requests.student_id = :student_id';
            $params['student_id'] = $studentId;
        }

        $sql .= ' ORDER BY student_requests.updated_at DESC, student_requests.id DESC LIMIT :limit';

        $statement = $this->database->connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        }
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<RequestRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @param array{request_id:int, author_user_id:int|null, visibility:string, body:string, created_at:string} $data
     */
    public function addNote(array $data): int
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO request_notes (request_id, author_user_id, visibility, body, created_at)
             VALUES (:request_id, :author_user_id, :visibility, :body, :created_at)'
        );
        $statement->execute($data);

        return (int) $this->database->connection()->lastInsertId();
    }

    /**
     * @return list<RequestNoteRow>
     */
    public function notes(int $requestId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT request_notes.*, users.name AS author_name
             FROM request_notes
             INNER JOIN users ON users.id = request_notes.author_user_id
             WHERE request_id = :request_id
             ORDER BY id DESC'
        );
        $statement->execute(['request_id' => $requestId]);

        /** @var list<RequestNoteRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @param array{request_id:int, note_id:int|null, uploaded_by_user_id:int|null, visibility:string, original_name:string, stored_name:string, mime_type:string, file_size:int, created_at:string} $data
     */
    public function addAttachment(array $data): int
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO request_attachments (
                request_id, note_id, uploaded_by_user_id, visibility, original_name, stored_name, mime_type, file_size, created_at
             ) VALUES (
                :request_id, :note_id, :uploaded_by_user_id, :visibility, :original_name, :stored_name, :mime_type, :file_size, :created_at
             )'
        );
        $statement->execute($data);

        return (int) $this->database->connection()->lastInsertId();
    }

    /**
     * @return list<RequestAttachmentRow>
     */
    public function attachments(int $requestId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT request_attachments.*, users.name AS uploaded_by_name
             FROM request_attachments
             INNER JOIN users ON users.id = request_attachments.uploaded_by_user_id
             WHERE request_id = :request_id
             ORDER BY id DESC'
        );
        $statement->execute(['request_id' => $requestId]);

        /** @var list<RequestAttachmentRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @return (RequestAttachmentRow&array{student_email?: string})|null
     */
    public function findAttachment(int $attachmentId): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT request_attachments.*, student_requests.student_id, students.email AS student_email
             FROM request_attachments
             INNER JOIN student_requests ON student_requests.id = request_attachments.request_id
             INNER JOIN students ON students.id = student_requests.student_id
             WHERE request_attachments.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $attachmentId]);
        $attachment = $statement->fetch();

        if (!is_array($attachment)) {
            return null;
        }

        /** @var RequestAttachmentRow&array{student_email?: string} $attachment */
        return $attachment;
    }
}
