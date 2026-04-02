<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\StudentRepository;
use App\Services\EnrollmentStatusService;
use App\Services\SearchService;
use App\Services\StatusService;
use App\Services\StudentService;
use RuntimeException;

final class StudentController
{
    public function __construct(
        private readonly Response $response,
        private readonly StudentService $students,
        private readonly StudentRepository $studentRepository,
        private readonly SearchService $search,
        private readonly Validator $validator,
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

        $students = $this->search->students($filters);
        if ($this->auth->primaryRole() === 'student') {
            $students = array_values(array_filter($students, fn (array $student): bool => $student['email'] === ($this->auth->user()['email'] ?? '')));
        }

        $this->response->view('students/index', [
            'students' => $students,
            'filters' => $filters,
            'departments' => $this->studentRepository->allDepartments(),
            'workflowStatuses' => StatusService::ALLOWED_STATUSES,
            'enrollmentStatuses' => EnrollmentStatusService::ALLOWED_STATUSES,
        ]);
    }

    public function create(): void
    {
        if ($this->auth->primaryRole() === 'student') {
            $this->response->redirect('/students', 'Students cannot create new profiles.', 'error');
        }

        $this->response->view('students/create', [
            'statuses' => StatusService::ALLOWED_STATUSES,
        ]);
    }

    public function store(): void
    {
        $input = map_value($_POST);
        [$errors, $data] = $this->validator->validate($input, $this->rules());
        /** @var StudentInput $studentData */
        $studentData = $data;
        $files = uploaded_file_value($_FILES['photo'] ?? null);

        if ($errors !== []) {
            $this->renderCreateForm($errors, 422);
        }

        try {
            $studentId = $this->students->create($studentData, $files !== [] ? ['photo' => $files] : []);
        } catch (RuntimeException $exception) {
            $this->renderCreateForm([
                'photo' => [$exception->getMessage()],
            ], 422);
        }

        $this->response->redirect('/students/' . $studentId, 'Student profile registered successfully.');
    }

    public function show(int $id): void
    {
        $student = $this->studentRepository->find($id);
        if ($student === null) {
            $this->response->view('partials/404', [], 404);
        }

        $this->authorizeStudentAccess($student);

        $this->response->view('students/show', [
            'student' => $student,
        ]);
    }

    public function edit(int $id): void
    {
        $student = $this->studentRepository->find($id);
        if ($student === null) {
            $this->response->view('partials/404', [], 404);
        }

        $this->authorizeStudentAccess($student);

        $this->response->view('students/edit', [
            'student' => $student,
        ]);
    }

    public function update(int $id): void
    {
        $input = map_value($_POST);
        [$errors, $data] = $this->validator->validate($input, $this->rules());
        /** @var StudentInput $studentData */
        $studentData = $data;
        $student = $this->studentRepository->find($id);
        $files = uploaded_file_value($_FILES['photo'] ?? null);

        if ($student === null) {
            $this->response->view('partials/404', [], 404);
        }

        $this->authorizeStudentAccess($student);

        if ($errors !== []) {
            $this->renderEditForm($student, $errors, 422);
        }

        try {
            $this->students->update($id, $studentData, $files !== [] ? ['photo' => $files] : []);
        } catch (RuntimeException $exception) {
            $this->renderEditForm($student, [
                'photo' => [$exception->getMessage()],
            ], 422);
        }

        $this->response->redirect('/students/' . $id, 'Student profile updated successfully.');
    }

    /**
     * @return ValidationRules
     */
    private function rules(): array
    {
        return [
            'first_name' => 'required',
            'last_name' => 'required',
            'birthdate' => 'required',
            'program' => 'required',
            'year_level' => 'required',
            'email' => ['required', 'email'],
            'phone' => 'required',
            'address' => 'required',
            'guardian_name' => 'required',
            'guardian_contact' => 'required',
            'department' => 'required',
        ];
    }

    /**
     * @param StudentRow $student
     */
    private function authorizeStudentAccess(array $student): void
    {
        if ($this->auth->primaryRole() === 'student' && $student['email'] !== ($this->auth->user()['email'] ?? '')) {
            $this->response->redirect('/students', 'You can only access your own profile.', 'error');
        }
    }

    /**
     * @param ValidationErrors $errors
     */
    private function renderCreateForm(array $errors, int $status = 422): never
    {
        $_SESSION['_old'] = $_POST;
        $this->response->view('students/create', [
            'errors' => $errors,
            'statuses' => StatusService::ALLOWED_STATUSES,
        ], $status);
    }

    /**
     * @param StudentRow $student
     * @param ValidationErrors $errors
     */
    private function renderEditForm(array $student, array $errors, int $status = 422): never
    {
        $_SESSION['_old'] = $_POST;
        $this->response->view('students/edit', [
            'errors' => $errors,
            'student' => array_merge($student, $_POST),
        ], $status);
    }
}
