<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcademicRecordRepository;
use App\Repositories\StudentRepository;

final class SearchService
{
    public function __construct(
        private readonly StudentRepository $students,
        private readonly AcademicRecordRepository $records
    ) {
    }

    /**
     * @param StudentFilters $filters
     * @return list<StudentRow>
     */
    public function students(array $filters): array
    {
        return $this->students->search($filters);
    }

    /**
     * @param array{student?: string, department?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function records(array $filters): array
    {
        return $this->records->search($filters);
    }
}
