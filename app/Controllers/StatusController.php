<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Repositories\StudentRepository;
use App\Services\EnrollmentStatusService;
use App\Services\StatusService;
use InvalidArgumentException;

final class StatusController
{
    public function __construct(
        private readonly Response $response,
        private readonly StudentRepository $students,
        private readonly StatusService $statuses,
        private readonly EnrollmentStatusService $enrollmentStatuses,
        private readonly Auth $auth
    ) {
    }

    public function index(): void
    {
        $filters = [
            'search' => trim(string_value($_GET['search'] ?? '')),
            'status' => trim(string_value($_GET['status'] ?? '')),
            'enrollment_status' => trim(string_value($_GET['enrollment_status'] ?? '')),
            'department' => trim(string_value($_GET['department'] ?? '')),
            'date_from' => trim(string_value($_GET['date_from'] ?? '')),
            'date_to' => trim(string_value($_GET['date_to'] ?? '')),
        ];

        $requests = $this->students->search($filters);
        if ($this->usesOwnStatusScope()) {
            $requests = array_values(array_filter($requests, fn (array $student): bool => $student['email'] === ($this->auth->user()['email'] ?? '')));
        }

        $this->response->view('statuses/index', [
            'requests' => $requests,
            'filters' => $filters,
            'departments' => $this->students->allDepartments(),
            'workflowStatuses' => StatusService::ALLOWED_STATUSES,
            'enrollmentStatuses' => EnrollmentStatusService::ALLOWED_STATUSES,
        ]);
    }

    public function show(int $id): void
    {
        $student = $this->students->find($id);
        if ($student === null) {
            $this->response->view('partials/404', [], 404);
        }

        if ($this->usesOwnStatusScope() && $student['email'] !== ($this->auth->user()['email'] ?? '')) {
            $this->response->redirect('/statuses', 'You can only view your own status timeline.', 'error');
        }

        $this->response->view('statuses/show', [
            'student' => $student,
            'workflowStatuses' => StatusService::ALLOWED_STATUSES,
            'enrollmentStatuses' => EnrollmentStatusService::ALLOWED_STATUSES,
        ]);
    }

    public function transition(int $id): void
    {
        $status = trim(string_value($_POST['status'] ?? ''));
        $remarks = trim(string_value($_POST['remarks'] ?? ''));

        try {
            $this->statuses->transition($id, $status, $remarks);
        } catch (InvalidArgumentException $exception) {
            $this->response->redirect('/statuses/' . $id, $exception->getMessage(), 'error');
        }

        $this->response->redirect('/statuses/' . $id, 'Status updated successfully.');
    }

    public function transitionEnrollment(int $id): void
    {
        $status = trim(string_value($_POST['enrollment_status'] ?? ''));
        $remarks = trim(string_value($_POST['remarks'] ?? ''));

        try {
            $this->enrollmentStatuses->transition($id, $status, $remarks);
        } catch (InvalidArgumentException $exception) {
            $this->response->redirect('/statuses/' . $id, $exception->getMessage(), 'error');
        }

        $this->response->redirect('/statuses/' . $id, 'Enrollment status updated successfully.');
    }

    private function usesOwnStatusScope(): bool
    {
        return $this->auth->can('statuses.view_own') && !$this->auth->can('statuses.view');
    }
}
