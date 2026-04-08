<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Repositories\NotificationRepository;
use App\Repositories\StudentRepository;
use App\Services\DashboardService;

final class DashboardController
{
    public function __construct(
        private readonly Response $response,
        private readonly DashboardService $dashboard,
        private readonly Auth $auth,
        private readonly StudentRepository $students,
        private readonly NotificationRepository $notifications
    ) {
    }

    public function index(): void
    {
        $this->response->view('dashboard/index', [
            'overview' => $this->dashboard->overview(
                $this->auth->user(),
                $this->usesOwnStudentDashboardScope()
                    ? $this->students->findByEmail((string) ($this->auth->user()['email'] ?? ''))
                    : null
            ),
            'notifications' => $this->auth->id() !== null ? $this->notifications->forUser((int) $this->auth->id()) : [],
        ]);
    }

    private function usesOwnStudentDashboardScope(): bool
    {
        return (
            $this->auth->can('students.view_own')
            || $this->auth->can('records.view_own')
            || $this->auth->can('statuses.view_own')
            || $this->auth->can('id_cards.view_own')
        ) && !$this->auth->can('students.view');
    }
}
