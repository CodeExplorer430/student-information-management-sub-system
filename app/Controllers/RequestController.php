<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Repositories\RequestRepository;
use App\Repositories\RoleRepository;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;
use App\Services\FileStorageService;
use App\Services\RequestService;
use InvalidArgumentException;
use RuntimeException;

final class RequestController
{
    public function __construct(
        private readonly Response $response,
        private readonly RequestRepository $requests,
        private readonly StudentRepository $students,
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly FileStorageService $storage,
        private readonly RequestService $requestService,
        private readonly Auth $auth
    ) {
    }

    public function index(): void
    {
        if (!$this->auth->can('requests.view_queue') && !$this->auth->can('requests.view_own')) {
            $this->response->redirect('/dashboard', 'You are not authorized to access this resource.', 'error');
        }

        $filters = [
            'search' => trim(string_value($_GET['search'] ?? '')),
            'status' => trim(string_value($_GET['status'] ?? '')),
            'request_type' => trim(string_value($_GET['request_type'] ?? '')),
            'priority' => trim(string_value($_GET['priority'] ?? '')),
            'department' => trim(string_value($_GET['department'] ?? '')),
            'overdue_only' => trim(string_value($_GET['overdue_only'] ?? '')),
        ];

        if ($this->auth->can('requests.view_own') && !$this->auth->can('requests.view_queue')) {
            $student = $this->students->findByEmail((string) ($this->auth->user()['email'] ?? ''));
            if ($student !== null) {
                $filters['student_id'] = (string) $student['id'];
            }
            $filters['created_by_user_id'] = (string) ($this->auth->id() ?? 0);
        }

        $this->response->view('requests/index', [
            'requests' => $this->requests->search($filters),
            'filters' => $filters,
            'requestTypes' => RequestService::REQUEST_TYPES,
            'requestStatuses' => RequestService::ALLOWED_STATUSES,
            'priorities' => RequestService::PRIORITIES,
            'departments' => $this->students->allDepartments(),
            'queueUsers' => $this->queueUsers(),
        ]);
    }

    public function create(): void
    {
        $student = $this->students->findByEmail((string) ($this->auth->user()['email'] ?? ''));
        if ($student === null) {
            $this->response->redirect('/dashboard', 'Student record not found for request creation.', 'error');
        }

        $this->response->view('requests/create', [
            'student' => $student,
            'requestTypes' => RequestService::REQUEST_TYPES,
            'priorities' => RequestService::PRIORITIES,
        ]);
    }

    public function store(): void
    {
        $student = $this->students->findByEmail((string) ($this->auth->user()['email'] ?? ''));
        if ($student === null) {
            $this->response->redirect('/dashboard', 'Student record not found for request creation.', 'error');
        }

        try {
            $requestId = $this->requestService->create(
                (int) $student['id'],
                trim(string_value($_POST['request_type'] ?? '')),
                trim(string_value($_POST['title'] ?? '')),
                trim(string_value($_POST['description'] ?? '')),
                trim(string_value($_POST['priority'] ?? 'Normal', 'Normal')),
                trim(string_value($_POST['due_at'] ?? '')) !== '' ? trim(string_value($_POST['due_at'] ?? '')) : null
            );
        } catch (InvalidArgumentException $exception) {
            $this->response->redirect('/requests/create', $exception->getMessage(), 'error');
        }

        $this->response->redirect('/requests/' . $requestId, 'Request submitted successfully.');
    }

    public function show(int $id): void
    {
        if (!$this->auth->can('requests.view_queue') && !$this->auth->can('requests.view_own')) {
            $this->response->redirect('/dashboard', 'You are not authorized to access this resource.', 'error');
        }

        $request = $this->requests->find($id);
        if ($request === null) {
            $this->response->view('partials/404', [], 404);
        }

        if ($this->auth->can('requests.view_own') && !$this->auth->can('requests.view_queue')) {
            $userEmail = (string) ($this->auth->user()['email'] ?? '');
            if (($request['student_email'] ?? '') !== $userEmail) {
                $this->response->redirect('/requests', 'You can only view your own requests.', 'error');
            }
        }

        $canManage = $this->auth->can('requests.transition');
        $canViewInternal = $canManage;

        $request['notes'] = array_values(array_filter(
            (array) ($request['notes'] ?? []),
            static fn (array $note): bool => $canViewInternal || (string) $note['visibility'] === 'student'
        ));
        $request['attachments'] = array_values(array_filter(
            (array) ($request['attachments'] ?? []),
            static fn (array $attachment): bool => $canViewInternal || (string) $attachment['visibility'] === 'student'
        ));

        $this->response->view('requests/show', [
            'request' => $request,
            'requestStatuses' => RequestService::ALLOWED_STATUSES,
            'priorities' => RequestService::PRIORITIES,
            'noteVisibilities' => $canManage ? RequestService::NOTE_VISIBILITIES : ['student'],
            'canManage' => $canManage,
            'queueUsers' => $this->queueUsers(),
        ]);
    }

    public function transition(int $id): void
    {
        try {
            $this->requestService->transition(
                $id,
                trim(string_value($_POST['status'] ?? '')),
                trim(string_value($_POST['remarks'] ?? '')),
                string_value($_POST['assigned_user_id'] ?? '') !== '' ? int_value($_POST['assigned_user_id'] ?? null) : null,
                trim(string_value($_POST['priority'] ?? '')) !== '' ? trim(string_value($_POST['priority'] ?? '')) : null,
                trim(string_value($_POST['due_at'] ?? '')) !== '' ? trim(string_value($_POST['due_at'] ?? '')) : null,
                trim(string_value($_POST['resolution_summary'] ?? '')) !== '' ? trim(string_value($_POST['resolution_summary'] ?? '')) : null
            );
        } catch (InvalidArgumentException $exception) {
            $this->response->redirect('/requests/' . $id, $exception->getMessage(), 'error');
        }

        $this->response->redirect('/requests/' . $id, 'Request updated successfully.');
    }

    public function addNote(int $id): void
    {
        $request = $this->requests->find($id);
        if ($request === null) {
            $this->response->view('partials/404', [], 404);
        }

        if (!$this->canAccessRequest($request)) {
            $this->response->redirect('/requests', 'You are not authorized to access this request.', 'error');
        }

        $visibility = trim(string_value($_POST['visibility'] ?? 'student', 'student'));
        if (!$this->auth->can('requests.transition')) {
            $visibility = 'student';
        }

        try {
            $attachment = $this->storage->storeAttachment(uploaded_file_value($_FILES['attachment'] ?? null));
            $this->requestService->addNote(
                $id,
                trim(string_value($_POST['body'] ?? '')),
                $visibility,
                $attachment
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->response->redirect('/requests/' . $id, $exception->getMessage(), 'error');
        }

        $this->response->redirect('/requests/' . $id, 'Request note added successfully.');
    }

    public function downloadAttachment(int $attachmentId): void
    {
        $attachment = $this->requests->findAttachment($attachmentId);
        if ($attachment === null) {
            $this->response->view('partials/404', [], 404);
        }

        if (!$this->auth->can('requests.view_queue') && !$this->auth->can('requests.view_own')) {
            $this->response->redirect('/dashboard', 'You are not authorized to access this resource.', 'error');
        }

        if (!$this->auth->can('requests.transition')) {
            $userEmail = (string) ($this->auth->user()['email'] ?? '');
            if ((string) ($attachment['student_email'] ?? '') !== $userEmail || (string) ($attachment['visibility'] ?? 'student') !== 'student') {
                $this->response->redirect('/requests', 'You are not authorized to access this attachment.', 'error');
            }
        }

        $path = $this->storage->pathFor(map_string($attachment, 'stored_name'));
        if (!is_file($path)) {
            $this->response->redirect('/requests/' . (int) ($attachment['request_id'] ?? 0), 'The requested attachment file is no longer available.', 'error');
        }

        $this->response->download(
            $path,
            string_value($attachment['original_name'] ?? basename($path), basename($path)),
            string_value($attachment['mime_type'] ?? 'application/octet-stream', 'application/octet-stream')
        );
    }

    /**
     * @return list<UserRow>
     */
    private function queueUsers(): array
    {
        return array_values(array_filter(
            $this->users->all(),
            fn (array $user): bool => in_array('requests.transition', $this->roles->permissionsForRoles($user['roles']), true)
        ));
    }

    /**
     * @param RequestRow $request
     */
    private function canAccessRequest(array $request): bool
    {
        if ($this->auth->can('requests.view_queue')) {
            return true;
        }

        if (!$this->auth->can('requests.view_own')) {
            return false;
        }

        return (string) ($request['student_email'] ?? '') === (string) ($this->auth->user()['email'] ?? '');
    }
}
