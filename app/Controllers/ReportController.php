<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Repositories\StudentRepository;
use App\Services\EnrollmentStatusService;
use App\Services\ReportService;
use App\Services\RequestService;
use App\Services\StatusService;

final class ReportController
{
    public function __construct(
        private readonly Response $response,
        private readonly ReportService $reports,
        private readonly StudentRepository $students
    ) {
    }

    public function index(): void
    {
        $filters = $this->filters();
        $dataset = in_array(($filters['dataset'] ?? 'requests'), ['students', 'requests', 'audits', 'notifications'], true)
            ? (string) $filters['dataset']
            : 'requests';

        $this->response->view('reports/index', [
            'overview' => $this->reports->overview($filters),
            'filters' => $filters,
            'dataset' => $dataset,
            'departments' => $this->students->allDepartments(),
            'workflowStatuses' => StatusService::ALLOWED_STATUSES,
            'enrollmentStatuses' => EnrollmentStatusService::ALLOWED_STATUSES,
            'requestStatuses' => RequestService::ALLOWED_STATUSES,
            'notificationChannels' => ['in_app', 'email', 'sms'],
            'notificationStatuses' => ['queued', 'sent', 'failed'],
        ]);
    }

    public function export(string $dataset): void
    {
        if (!in_array($dataset, ['students', 'requests', 'audits', 'notifications'], true)) {
            $this->response->redirect('/reports', 'Unsupported report dataset.', 'error');
        }

        $rows = $this->reports->exportRows($dataset, $this->filters());
        $csv = $this->toCsv($dataset, $rows);

        $this->response->downloadContent(
            $csv,
            sprintf('%s-report-%s.csv', $dataset, date('Ymd-His')),
            'text/csv; charset=UTF-8'
        );
    }

    /**
     * @return array{dataset: string, search: string, status: string, request_type: string, enrollment_status: string, department: string, date_from: string, date_to: string, channel: string}
     */
    private function filters(): array
    {
        return [
            'dataset' => trim(string_value($_GET['dataset'] ?? 'requests', 'requests')),
            'search' => trim(string_value($_GET['search'] ?? '')),
            'status' => trim(string_value($_GET['status'] ?? '')),
            'request_type' => trim(string_value($_GET['request_type'] ?? '')),
            'enrollment_status' => trim(string_value($_GET['enrollment_status'] ?? '')),
            'department' => trim(string_value($_GET['department'] ?? '')),
            'date_from' => trim(string_value($_GET['date_from'] ?? '')),
            'date_to' => trim(string_value($_GET['date_to'] ?? '')),
            'channel' => trim(string_value($_GET['channel'] ?? '')),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function toCsv(string $dataset, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }
        /** @var resource $stream */

        $headers = match ($dataset) {
            'students' => ['student_number', 'first_name', 'last_name', 'program', 'department', 'latest_status', 'enrollment_status'],
            'requests' => ['title', 'request_type', 'student_number', 'status', 'assigned_name', 'submitted_at'],
            'audits' => ['actor_name', 'entity_type', 'entity_id', 'action', 'created_at'],
            'notifications' => ['title', 'user_name', 'channel', 'recipient', 'status', 'created_at'],
            default => [],
        };

        if ($headers !== []) {
            fputcsv($stream, $headers, ',', '"', '');
        }

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $this->csvCell(string_value($row[$header] ?? ''));
            }
            fputcsv($stream, $line, ',', '"', '');
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^\s*[=+\-@\t\r\n]/', $value) === 1 ? "'" . $value : $value;
    }
}
