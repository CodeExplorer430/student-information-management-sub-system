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
        if (!$this->auth->can('records.view')) {
            $this->response->redirect('/dashboard', 'You are not authorized to access academic records.', 'error');
        }

        $filters = [
            'student' => trim(string_value($_GET['student'] ?? '')),
            'department' => trim(string_value($_GET['department'] ?? '')),
        ];

        if ($this->auth->primaryRole() === 'student') {
            $filters['student'] = $this->auth->user()['email'] ?? '';
        }

        $this->response->view('records/index', [
            'records' => $this->search->records($filters),
            'filters' => $filters,
            'departments' => $this->students->allDepartments(),
        ]);
    }

    public function show(int $id): void
    {
        if (!$this->auth->can('records.view')) {
            $this->response->redirect('/dashboard', 'You are not authorized to access academic records.', 'error');
        }

        $student = $this->students->find($id);
        if ($student === null) {
            $this->response->view('partials/404', [], 404);
        }

        if ($this->auth->primaryRole() === 'student' && $student['email'] !== ($this->auth->user()['email'] ?? '')) {
            $this->response->redirect('/records', 'You can only view your own records.', 'error');
        }

        $this->response->view('records/show', [
            'student' => $student,
            'records' => $this->records->forStudent($id),
        ]);
    }

    public function export(int $id): void
    {
        if (!$this->auth->can('records.view')) {
            $this->response->redirect('/dashboard', 'You are not authorized to export academic records.', 'error');
        }

        $student = $this->students->find($id);
        if ($student === null) {
            $this->response->view('partials/404', [], 404);
        }

        if ($this->auth->primaryRole() === 'student' && $student['email'] !== ($this->auth->user()['email'] ?? '')) {
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
                map_string($row, 'term_label'),
                map_string($row, 'subject_code'),
                map_string($row, 'subject_title'),
                string_value($row['units'] ?? ''),
                map_string($row, 'grade'),
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
}
