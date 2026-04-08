<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Repositories\StudentRepository;
use App\Services\EnrollmentStatusService;
use App\Services\IdCardService;
use App\Services\StatusService;
use RuntimeException;

final class IdCardController
{
    public function __construct(
        private readonly Response $response,
        private readonly IdCardService $idCards,
        private readonly StudentRepository $students,
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
        ];

        $students = $this->students->search($filters);
        if ($this->usesOwnIdCardScope()) {
            $students = array_values(array_filter($students, fn (array $student): bool => $student['email'] === ($this->auth->user()['email'] ?? '')));
        }

        $this->response->view('id-cards/index', [
            'students' => $students,
            'filters' => $filters,
            'departments' => $this->students->allDepartments(),
            'workflowStatuses' => StatusService::ALLOWED_STATUSES,
            'enrollmentStatuses' => EnrollmentStatusService::ALLOWED_STATUSES,
        ]);
    }

    public function generate(): void
    {
        $studentId = int_value($_POST['student_id'] ?? 0);

        try {
            $this->idCards->generate($studentId, $this->auth->id());
        } catch (RuntimeException $exception) {
            $this->response->redirect('/id-cards', $exception->getMessage(), 'error');
        }

        $this->response->redirect('/id-cards/' . $studentId . '/print', 'Student ID generated successfully.');
    }

    public function download(int $id): void
    {
        $student = $this->students->find($id);
        $card = $this->idCards->latestCard($id);
        if ($student === null || $card === null) {
            $this->response->redirect('/id-cards', 'Generate the ID card first.', 'error');
        }

        $this->authorizeCardAccess($student, 'download your own ID');

        $path = dirname(__DIR__, 2) . '/storage/app/public/id-cards/' . $card['file_path'];
        if (!file_exists($path)) {
            $this->response->redirect('/id-cards', 'The generated ID file is no longer available.', 'error');
        }

        $this->response->download($path, basename($path), 'image/png');
    }

    public function printView(int $id): void
    {
        $student = $this->students->find($id);
        $card = $this->idCards->latestCard($id);

        if ($student === null || $card === null) {
            $this->response->redirect('/id-cards', 'Generate the ID card first.', 'error');
        }

        $this->authorizeCardAccess($student, 'preview your own ID');

        $path = dirname(__DIR__, 2) . '/storage/app/public/id-cards/' . $card['file_path'];
        if (!file_exists($path)) {
            $this->response->redirect('/id-cards', 'The generated ID file is no longer available.', 'error');
        }

        $card['image_data'] = base64_encode((string) file_get_contents($path));

        $this->response->view('id-cards/show', [
            'student' => $student,
            'card' => $card,
        ]);
    }

    public function verify(int $id): void
    {
        $student = $this->students->find($id);
        $card = $this->idCards->latestCard($id);

        if ($student === null || $card === null) {
            $this->response->view('partials/404', [], 404);
        }

        $this->response->view('id-cards/verify', [
            'student' => $student,
            'card' => $card,
        ]);
    }

    /**
     * @param StudentRow $student
     */
    private function authorizeCardAccess(array $student, string $action): void
    {
        if (!$this->auth->can('id_cards.view') && !$this->auth->can('id_cards.view_own')) {
            $this->response->redirect('/dashboard', 'You are not authorized to access this resource.', 'error');
        }

        if ($this->usesOwnIdCardScope() && $student['email'] !== ($this->auth->user()['email'] ?? '')) {
            $this->response->redirect('/id-cards', 'You can only ' . $action . '.', 'error');
        }
    }

    private function usesOwnIdCardScope(): bool
    {
        return $this->auth->can('id_cards.view_own') && !$this->auth->can('id_cards.view');
    }
}
