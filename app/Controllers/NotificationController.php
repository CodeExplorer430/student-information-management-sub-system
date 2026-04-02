<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Repositories\NotificationRepository;

final class NotificationController
{
    public function __construct(
        private readonly Response $response,
        private readonly NotificationRepository $notifications,
        private readonly Auth $auth
    ) {
    }

    public function index(): void
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            $this->response->redirect('/login', 'Please sign in first.', 'error');
        }

        $this->response->view('notifications/index', [
            'notifications' => $this->notifications->forUser($userId),
        ]);
    }

    public function markAllRead(): void
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            $this->response->redirect('/login', 'Please sign in first.', 'error');
        }

        $this->notifications->markAllRead($userId);

        $this->response->back('/notifications', 'Notifications marked as read.');
    }
}
