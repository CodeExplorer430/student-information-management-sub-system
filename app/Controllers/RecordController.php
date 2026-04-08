<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Repositories\AcademicRecordRepository;
use App\Repositories\StudentRepository;
use App\Services\SearchService;

final class RecordController
{
    public function __construct(
        private readonly Response $response,
        private readonly AcademicRecordRepository $records,
        private readonly SearchService $search,
        private readonly StudentRepository $students,
        private readonly Auth $auth
    ) {
    }

    public function index(): void
    {
        if (!$this->auth->can('records.view') && !$this->auth->can('records.view_own')) {
            $this->response->redirect('/dashboard', 'You are not authorized to access academic records.', 'error');
        }

        $filters = [
            'student' => trim(string_value($_GET['student'] ?? '')),
            'department' => trim(string_value($_GET['department'] ?? '')),
        ];

        if ($this->usesOwnRecordScope()) {
            $filters['student'] = $this->auth->user()['email'] ?? '';
        }

        $page = max(1, (int) string_value($_GET['page'] ?? '1', '1'));
        $perPage = 15;
        $allRecords = $this->search->records($filters);
        $total = count($allRecords);
        $pageCount = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;
        $records = array_slice($allRecords, $offset, $perPage);
        $from = $records === [] ? 0 : $offset + 1;
        $to = $records === [] ? 0 : $offset + count($records);

        $this->response->view('records/index', [
            'records' => $records,
            'filters' => $filters,
            'departments' => $this->students->allDepartments(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'page_count' => $pageCount,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function show(int $id): void
    {
        if (!$this->auth->can('records.view') && !$this->auth->can('records.view_own')) {
            $this->response->redirect('/dashboard', 'You are not authorized to access academic records.', 'error');
        }

        $student = $this->students->find($id);
        if ($student === null) {
            $this->response->view('partials/404', [], 404);
        }

        if ($this->usesOwnRecordScope() && $student['email'] !== ($this->auth->user()['email'] ?? '')) {
            $this->response->redirect('/records', 'You can only view your own records.', 'error');
        }

        $this->response->view('records/show', [
            'student' => $student,
            'records' => $this->records->forStudent($id),
        ]);
    }

    public function export(int $id): void
    {
        if (!$this->auth->can('records.view') && !$this->auth->can('records.view_own')) {
            $this->response->redirect('/dashboard', 'You are not authorized to export academic records.', 'error');
        }

        $student = $this->students->find($id);
        if ($student === null) {
            $this->response->view('partials/404', [], 404);
        }

        if ($this->usesOwnRecordScope() && $student['email'] !== ($this->auth->user()['email'] ?? '')) {
            $this->response->redirect('/records', 'You can only export your own records.', 'error');
        }

        $rows = $this->records->forStudent($id);
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            $this->response->redirect('/records/' . $id, 'Unable to prepare the academic record export.', 'error');
        }
        /** @var resource $stream */

        fputcsv($stream, ['term_label', 'subject_code', 'subject_title', 'units', 'grade'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, [
                $this->csvCell(map_string($row, 'term_label')),
                $this->csvCell(map_string($row, 'subject_code')),
                $this->csvCell(map_string($row, 'subject_title')),
                $this->csvCell(string_value($row['units'] ?? '')),
                $this->csvCell(map_string($row, 'grade')),
            ], ',', '"', '');
        }
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        $this->response->downloadContent(
            $csv,
            sprintf('academic-records-%s.csv', string_value($student['student_number'] ?? $id)),
            'text/csv; charset=UTF-8'
        );
    }

    private function usesOwnRecordScope(): bool
    {
        return $this->auth->can('records.view_own') && !$this->auth->can('records.view');
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^\s*[=+\-@\t\r\n]/', $value) === 1 ? "'" . $value : $value;
    }
}
