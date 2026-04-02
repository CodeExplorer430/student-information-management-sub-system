<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StudentRepository;
use RuntimeException;

final class StudentService
{
    public function __construct(
        private readonly StudentRepository $students,
        private readonly FileStorageService $files,
        private readonly StatusService $statuses,
        private readonly AuditService $auditService
    ) {
    }

    /**
     * @param StudentInput $data
     * @param array{photo?: array{name?: string, tmp_name?: string, error?: int, size?: int|false}} $files
     */
    public function create(array $data, array $files): int
    {
        $data = $this->validatedStudentInput($data);
        $timestamp = date('Y-m-d H:i:s');
        $studentNumber = $this->generateStudentNumber($data['department']);
        $photoPath = $this->files->storeImage($files['photo'] ?? []);

        $payload = [
            'student_number' => $studentNumber,
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? '',
            'last_name' => $data['last_name'],
            'birthdate' => $data['birthdate'],
            'program' => $data['program'],
            'year_level' => $data['year_level'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'guardian_name' => $data['guardian_name'],
            'guardian_contact' => $data['guardian_contact'],
            'department' => $data['department'],
            'enrollment_status' => 'Active',
            'photo_path' => $photoPath,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];

        $studentId = $this->students->create($payload);
        $this->students->addEnrollmentStatusHistory($studentId, 'Active', 'Student admitted and eligible for enrollment.', null, $timestamp);
        $this->statuses->transition($studentId, 'Pending', 'Student profile created.');
        $this->auditService->log('student', $studentId, 'created', null, $payload);

        return $studentId;
    }

    /**
     * @param StudentInput $data
     * @param array{photo?: array{name?: string, tmp_name?: string, error?: int, size?: int|false}} $files
     */
    public function update(int $id, array $data, array $files): void
    {
        $data = $this->validatedStudentInput($data);
        $student = $this->students->find($id);
        if ($student === null) {
            throw new RuntimeException('Student not found.');
        }

        $photoPath = $student['photo_path'];
        $uploaded = $this->files->storeImage($files['photo'] ?? []);
        if ($uploaded !== null) {
            $photoPath = $uploaded;
        }

        $payload = [
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? '',
            'last_name' => $data['last_name'],
            'birthdate' => $data['birthdate'],
            'program' => $data['program'],
            'year_level' => $data['year_level'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'guardian_name' => $data['guardian_name'],
            'guardian_contact' => $data['guardian_contact'],
            'department' => $data['department'],
            'photo_path' => $photoPath,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->students->update($id, $payload);
        $this->auditService->log('student', $id, 'updated', $student, $payload);
    }

    private function generateStudentNumber(string $department): string
    {
        $sanitizedDepartment = preg_replace('/[^A-Za-z]/', '', $department) ?? '';
        $prefix = strtoupper(substr($sanitizedDepartment, 0, 3) ?: 'STU');
        $year = date('Y');
        $sequence = $this->students->nextSequenceForPrefixYear($prefix, $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }

    /**
     * @param StudentInput $data
     * @return array{
     *     first_name: string,
     *     middle_name?: string|null,
     *     last_name: string,
     *     birthdate: string,
     *     program: string,
     *     year_level: string,
     *     email: string,
     *     phone: string,
     *     address: string,
     *     guardian_name: string,
     *     guardian_contact: string,
     *     department: string
     * }
     */
    private function validatedStudentInput(array $data): array
    {
        $firstName = $this->requiredString($data, 'first_name');
        $lastName = $this->requiredString($data, 'last_name');
        $birthdate = $this->requiredString($data, 'birthdate');
        $program = $this->requiredString($data, 'program');
        $yearLevel = $this->requiredString($data, 'year_level');
        $email = $this->requiredString($data, 'email');
        $phone = $this->requiredString($data, 'phone');
        $address = $this->requiredString($data, 'address');
        $guardianName = $this->requiredString($data, 'guardian_name');
        $guardianContact = $this->requiredString($data, 'guardian_contact');
        $department = $this->requiredString($data, 'department');

        return [
            'first_name' => $firstName,
            'middle_name' => $data['middle_name'] ?? '',
            'last_name' => $lastName,
            'birthdate' => $birthdate,
            'program' => $program,
            'year_level' => $yearLevel,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'guardian_name' => $guardianName,
            'guardian_contact' => $guardianContact,
            'department' => $department,
        ];
    }

    /**
     * @param StudentInput $data
     */
    private function requiredString(array $data, string $field): string
    {
        $value = $data[$field] ?? null;

        if (!is_string($value)) {
            throw new RuntimeException(sprintf('Student payload is missing [%s].', $field));
        }

        return $value;
    }
}
