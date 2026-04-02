<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class AcademicRecordRepository
{
    public function __construct(
        private readonly Database $database
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forStudent(int $studentId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT * FROM academic_records WHERE student_id = :student_id ORDER BY term_label DESC, subject_code ASC'
        );
        $statement->execute(['student_id' => $studentId]);

        /** @var list<AcademicRecordRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @param array{student?: string, department?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function search(array $filters): array
    {
        $conditions = [];
        $params = [];
        $sql = 'SELECT academic_records.*, students.student_number, students.first_name, students.last_name, students.department
                FROM academic_records
                INNER JOIN students ON students.id = academic_records.student_id';

        if (!empty($filters['student'])) {
            $conditions[] = '(students.student_number LIKE :student OR students.first_name LIKE :student OR students.last_name LIKE :student OR students.email LIKE :student)';
            $params['student'] = '%' . $filters['student'] . '%';
        }

        if (!empty($filters['department'])) {
            $conditions[] = 'students.department = :department';
            $params['department'] = $filters['department'];
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY academic_records.term_label DESC, students.last_name ASC';

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($params);

        /** @var list<AcademicRecordRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }
}
