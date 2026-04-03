<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\NotificationRepository;

final class View
{
    public function __construct(
        private readonly string $viewsPath,
        private readonly Session $session,
        private readonly Csrf $csrf,
        private readonly Auth $auth,
        private readonly Config $config,
        private readonly NotificationRepository $notifications
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $payload = array_merge($this->sharedData(), $data, [
            'old' => $this->session->get('_old', []),
            'flashMessages' => $this->pullFlashMessages(),
        ]);
        $this->session->forget('_old');

        $view = new ViewContext($this);
        $view->setData($payload);

        $content = $this->evaluate($template, $payload, $view);
        if ($view->layoutTemplate() === null) {
            return $content;
        }

        if (!$view->hasSection('content') && trim($content) !== '') {
            $view->setSection('content', $content);
        }

        $layoutData = array_merge($payload, $view->layoutData());
        $view->setData($layoutData);

        /** @var string $layoutTemplate */
        $layoutTemplate = $view->layoutTemplate();
        return $this->evaluate($layoutTemplate, $layoutData, $view);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderPartial(string $template, array $data = []): string
    {
        $view = new ViewContext($this);
        $view->setData($data);

        return $this->evaluate($template, $data, $view);
    }

    /**
     * @return list<FlashMessage>
     */
    private function pullFlashMessages(): array
    {
        $messages = $this->session->get('flash.messages', []);
        $this->session->forget('flash.messages');

        if (!is_array($messages)) {
            return [];
        }

        return array_values(array_filter(
            $messages,
            static fn (mixed $message): bool => is_array($message)
                && is_string($message['type'] ?? null)
                && is_string($message['message'] ?? null)
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedData(): array
    {
        $user = $this->auth->user();

        return [
            'app' => [
                'name' => string_value($this->config->get('app.name', 'Bestlink SIS'), 'Bestlink SIS'),
                'user' => $user,
                'url' => string_value($this->config->get('app.url', 'http://127.0.0.1:8000'), 'http://127.0.0.1:8000'),
                'permissions' => $this->auth->permissions(),
                'notification_unread_count' => $user !== null ? $this->notifications->unreadCount((int) ($user['id'] ?? 0)) : 0,
            ],
            'csrf' => $this->csrf->token(),
            'current_path' => strtok(string_value($_SERVER['REQUEST_URI'] ?? '/', '/'), '?') ?: '/',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function evaluate(string $template, array $data, ViewContext $view): string
    {
        $path = $this->resolvePath($template);

        ob_start();

        (static function (string $__path, array $__data, ViewContext $__view): void {
            extract($__data, EXTR_SKIP);
            $view = $__view;

            require $__path;
        })($path, $data, $view);

        return (string) ob_get_clean();
    }

    private function resolvePath(string $template): string
    {
        $relativePath = ltrim($template, '/');
        if (!str_ends_with($relativePath, '.php')) {
            $relativePath .= '.php';
        }

        $path = rtrim($this->viewsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('View template [%s] was not found.', $template));
        }

        return $path;
    }
}
