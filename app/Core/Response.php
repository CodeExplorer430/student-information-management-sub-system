<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private readonly View $view,
        private readonly Flash $flash
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function view(string $template, array $data = [], int $status = 200): never
    {
        throw new HttpResultException(HttpResult::html(
            $this->view->render($template, $data),
            $status
        ));
    }

    public function redirect(string $path, ?string $message = null, string $type = 'success'): never
    {
        if ($message !== null) {
            $this->flash->add($type, $message);
        }

        throw new HttpResultException(HttpResult::redirect($path));
    }

    public function back(string $fallback, ?string $message = null, string $type = 'error'): never
    {
        $target = $_POST['_back'] ?? $_SERVER['HTTP_REFERER'] ?? $fallback;

        if (!is_string($target) || $target === '') {
            $target = $fallback;
        }

        $path = parse_url($target, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $fallback;
        }

        $query = parse_url($target, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $path .= '?' . $query;
        }

        $this->redirect($path, $message, $type);
    }

    public function download(string $path, string $fileName, string $contentType = 'application/octet-stream'): never
    {
        $content = is_file($path) ? file_get_contents($path) : false;

        throw new HttpResultException(HttpResult::download(
            is_string($content) ? $content : '',
            $fileName,
            $contentType
        ));
    }

    public function downloadContent(string $content, string $fileName, string $contentType = 'application/octet-stream'): never
    {
        throw new HttpResultException(HttpResult::download($content, $fileName, $contentType));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function json(array $payload, int $status = 200): never
    {
        throw new HttpResultException(HttpResult::json($payload, $status));
    }
}
