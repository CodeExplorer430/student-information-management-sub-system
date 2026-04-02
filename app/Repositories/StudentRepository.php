<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class StudentRepository
{
    public function __construct(
        private readonly Database $database
    ) {
    }

    /**
     * @param StudentFilters $filters
     * @return list<StudentRow>
     */
    public function search(array $filters = []): array
    {
        $conditions = [];
        $params = [];
        $sql = 'SELECT students.*, latest.status AS latest_status, latest.created_at AS latest_status_at,
                       latest_id_card.file_path AS id_card_path, latest_id_card.generated_at AS id_generated_at
                FROM students
                LEFT JOIN (
                    SELECT status_histories.student_id, status_histories.status, status_histories.created_at
                    FROM status_histories
                    INNER JOIN (
                        SELECT student_id, MAX(id) AS max_id
                        FROM status_histories
                        GROUP BY student_id
                    ) current_status
                    ON current_status.max_id = status_histories.id
                ) latest ON latest.student_id = students.id
                LEFT JOIN (
                    SELECT id_cards.student_id, id_cards.file_path, id_cards.generated_at
                    FROM id_cards
                    INNER JOIN (
                        SELECT student_id, MAX(id) AS max_id
                        FROM id_cards
                        GROUP BY student_id
                    ) current_id_card
                    ON current_id_card.max_id = id_cards.id
                ) latest_id_card ON latest_id_card.student_id = students.id';

        if (!empty($filters['search'])) {
            $conditions[] = '(students.student_number LIKE :search OR students.first_name LIKE :search OR students.last_name LIKE :search OR students.email LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'latest.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['enrollment_status'])) {
            $conditions[] = 'students.enrollment_status = :enrollment_status';
            $params['enrollment_status'] = $filters['enrollment_status'];
        }

        if (!empty($filters['department'])) {
            $conditions[] = 'students.department = :department';
            $params['department'] = $filters['department'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'students.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'students.created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY students.created_at DESC';

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($params);

        /** @var list<StudentRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function allDepartments(): array
    {
        $rows = $this->database->query('SELECT DISTINCT department FROM students WHERE department <> "" ORDER BY department ASC')
            ->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter($rows, static fn ($value): bool => is_string($value) && $value !== ''));
    }

    public function count(): int
    {
        return (int) $this->database->query('SELECT COUNT(*) FROM students')->fetchColumn();
    }

    /**
     * @return StudentRow|null
     */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->database->connection()->prepare('SELECT * FROM students WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $student = $statement->fetch();

        if (!is_array($student)) {
            return null;
        }

        /** @var StudentRow $student */
        return $student;
    }

    public function nextSequenceForPrefixYear(string $prefix, string $year): int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT student_number FROM students WHERE student_number LIKE :pattern ORDER BY student_number ASC'
        );
        $statement->execute([
            'pattern' => sprintf('%s-%s-%%', $prefix, $year),
        ]);

        $highest = 1000;

        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $studentNumber) {
            if (!is_string($studentNumber)) {
                continue;
            }

            $suffix = (int) substr($studentNumber, strrpos($studentNumber, '-') + 1);
            if ($suffix > $highest) {
                $highest = $suffix;
            }
        }

        return $highest + 1;
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $rows = $this->database->query(
            'SELECT latest.status, COUNT(*) AS total
             FROM (
                SELECT student_id, MAX(id) AS max_id
                FROM status_histories
                GROUP BY student_id
             ) current_status
             INNER JOIN status_histories latest
                ON latest.id = current_status.max_id
             GROUP BY latest.status'
        )->fetchAll(PDO::FETCH_ASSOC);

        $counts = [];
        foreach (rows_value($rows) as $row) {
            $counts[map_string($row, 'status')] = map_int($row, 'total');
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function countByEnrollmentStatus(): array
    {
        $rows = $this->database->query(
            'SELECT enrollment_status, COUNT(*) AS total
             FROM students
             GROUP BY enrollment_status
             ORDER BY enrollment_status ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $counts = [];
        foreach (rows_value($rows) as $row) {
            $counts[map_string($row, 'enrollment_status')] = map_int($row, 'total');
        }

        return $counts;
    }

    /**
     * @return StudentRow|null
     */
    public function find(int $id): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT students.*,
                id_cards.file_path AS id_card_path,
                id_cards.generated_at AS id_generated_at
             FROM students
             LEFT JOIN id_cards ON id_cards.student_id = students.id
             WHERE students.id = :id
             ORDER BY id_cards.generated_at DESC
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $student = $statement->fetch();

        if (!is_array($student)) {
            return null;
        }

        $student['status_history'] = $this->statusHistory($id);
        $student['latest_status'] = map_string($student['status_history'][0] ?? [], 'status', 'Pending');
        $student['enrollment_status_history'] = $this->enrollmentStatusHistory($id);
        $student['audit_logs'] = $this->auditLogs($id);

        /** @var StudentRow $student */
        return $student;
    }

    /**
     * @param StudentInput $data
     */
    public function create(array $data): int
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO students (
                student_number, first_name, middle_name, last_name, birthdate, program, year_level,
                email, phone, address, guardian_name, guardian_contact, department, enrollment_status, photo_path, created_at, updated_at
             ) VALUES (
                :student_number, :first_name, :middle_name, :last_name, :birthdate, :program, :year_level,
                :email, :phone, :address, :guardian_name, :guardian_contact, :department, :enrollment_status, :photo_path, :created_at, :updated_at
             )'
        );
        $statement->execute($data);

        return (int) $this->database->connection()->lastInsertId();
    }

    /**
     * @param StudentInput $data
     */
    public function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $statement = $this->database->connection()->prepare(
            'UPDATE students SET
                first_name = :first_name,
                middle_name = :middle_name,
                last_name = :last_name,
                birthdate = :birthdate,
                program = :program,
                year_level = :year_level,
                email = :email,
                phone = :phone,
                address = :address,
                guardian_name = :guardian_name,
                guardian_contact = :guardian_contact,
                department = :department,
                photo_path = :photo_path,
                updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute($data);
    }

    public function addStatusHistory(int $studentId, string $status, string $remarks, ?int $assignedUserId, string $createdAt): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO status_histories (student_id, status, remarks, assigned_user_id, created_at)
             VALUES (:student_id, :status, :remarks, :assigned_user_id, :created_at)'
        );
        $statement->execute([
            'student_id' => $studentId,
            'status' => $status,
            'remarks' => $remarks,
            'assigned_user_id' => $assignedUserId,
            'created_at' => $createdAt,
        ]);
    }

    public function updateEnrollmentStatus(int $studentId, string $status): void
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE students SET enrollment_status = :enrollment_status, updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute([
            'enrollment_status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => $studentId,
        ]);
    }

    public function addEnrollmentStatusHistory(int $studentId, string $status, string $remarks, ?int $assignedUserId, string $createdAt): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO enrollment_status_histories (student_id, status, remarks, assigned_user_id, created_at)
             VALUES (:student_id, :status, :remarks, :assigned_user_id, :created_at)'
        );
        $statement->execute([
            'student_id' => $studentId,
            'status' => $status,
            'remarks' => $remarks,
            'assigned_user_id' => $assignedUserId,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * @return list<StatusHistoryRow&array{assigned_personnel?: string|null}>
     */
    public function statusHistory(int $studentId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT status_histories.*, users.name AS assigned_personnel
             FROM status_histories
             LEFT JOIN users ON users.id = status_histories.assigned_user_id
             WHERE student_id = :student_id
             ORDER BY id DESC'
        );
        $statement->execute(['student_id' => $studentId]);

        /** @var list<StatusHistoryRow&array{assigned_personnel?: string|null}> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @return list<EnrollmentStatusHistoryRow&array{assigned_personnel?: string|null}>
     */
    public function enrollmentStatusHistory(int $studentId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT enrollment_status_histories.*, users.name AS assigned_personnel
             FROM enrollment_status_histories
             LEFT JOIN users ON users.id = enrollment_status_histories.assigned_user_id
             WHERE student_id = :student_id
             ORDER BY id DESC'
        );
        $statement->execute(['student_id' => $studentId]);

        /** @var list<EnrollmentStatusHistoryRow&array{assigned_personnel?: string|null}> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    public function saveIdCard(int $studentId, string $filePath, string $qrPayload, string $barcodePayload, ?int $generatedBy): void
    {
        $cleanup = $this->database->connection()->prepare('DELETE FROM id_cards WHERE student_id = :student_id');
        $cleanup->execute(['student_id' => $studentId]);

        $statement = $this->database->connection()->prepare(
            'INSERT INTO id_cards (student_id, file_path, qr_payload, barcode_payload, generated_by, generated_at)
             VALUES (:student_id, :file_path, :qr_payload, :barcode_payload, :generated_by, :generated_at)'
        );
        $statement->execute([
            'student_id' => $studentId,
            'file_path' => $filePath,
            'qr_payload' => $qrPayload,
            'barcode_payload' => $barcodePayload,
            'generated_by' => $generatedBy,
            'generated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{student_id:int, file_path:string, qr_payload:string, barcode_payload:string, generated_by:int|null, generated_at:string}|null
     */
    public function latestIdCard(int $studentId): ?array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM id_cards WHERE student_id = :student_id ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['student_id' => $studentId]);
        $card = $statement->fetch();

        if (!is_array($card)) {
            return null;
        }

        /** @var array{student_id:int, file_path:string, qr_payload:string, barcode_payload:string, generated_by:int|null, generated_at:string} $card */
        return $card;
    }

    /**
     * @return list<array{id:int, student_number:string, first_name:string, last_name:string, program:string, department:string, enrollment_status:string}>
     */
    public function recent(int $limit = 5): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT students.id, students.student_number, students.first_name, students.last_name, students.program, students.department, students.enrollment_status
             FROM students
             ORDER BY students.created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<RecentStudentRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @return list<AuditLogRow&array{actor_name?: string|null}>
     */
    private function auditLogs(int $studentId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT audit_logs.*, users.name AS actor_name
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.user_id
             WHERE entity_type = :entity_type AND entity_id = :entity_id
             ORDER BY created_at DESC'
        );
        $statement->execute([
            'entity_type' => 'student',
            'entity_id' => $studentId,
        ]);

        /** @var list<AuditLogRow&array{actor_name?: string|null}> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }
}
