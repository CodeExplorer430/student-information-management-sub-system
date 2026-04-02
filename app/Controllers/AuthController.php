<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use RuntimeException;

final class AuthController
{
    public function __construct(
        private readonly Response $response,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session
    ) {
    }

    public function showLogin(): void
    {
        if ($this->auth->check()) {
            $this->response->redirect('/dashboard');
        }

        $loginNotice = $this->session->pull('auth.login_notice');
        $flashMessages = $this->session->get('flash.messages', []);
        if ($loginNotice === null && is_array($flashMessages)) {
            $flashMessages = array_values(array_filter(
                $flashMessages,
                static fn (mixed $message): bool => !is_array($message)
                    || (($message['message'] ?? null) !== 'Please sign in first.')
            ));
            $this->session->set('flash.messages', $flashMessages);
        }

        $this->response->view('auth/login', [
            'loginNotice' => is_string($loginNotice) ? $loginNotice : null,
        ]);
    }

    public function login(): void
    {
        try {
            $this->csrf->validate(nullable_string_value($_POST['_csrf'] ?? null));
        } catch (RuntimeException) {
            $this->response->back('/login', 'Your session security token is invalid or expired. Please sign in again.', 'error');
        }

        $email = trim(string_value($_POST['email'] ?? ''));
        $password = string_value($_POST['password'] ?? '');

        if (!$this->auth->attempt($email, $password)) {
            $this->response->redirect('/login', 'Invalid credentials.', 'error');
        }

        $this->response->redirect('/dashboard', 'Welcome back.');
    }

    public function logout(): void
    {
        $this->auth->logout();
        $this->response->redirect('/login', 'You have been signed out.');
    }
}
