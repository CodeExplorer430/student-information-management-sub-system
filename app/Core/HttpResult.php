<?php

declare(strict_types=1);

namespace App\Core;

final class HttpResult
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $status,
        private readonly string $body,
        private readonly array $headers = []
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $body, array_merge([
            'Content-Type' => 'text/html; charset=UTF-8',
        ], $headers));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public static function json(array $payload, int $status = 200, array $headers = []): self
    {
        return new self($status, json_encode($payload, JSON_THROW_ON_ERROR), array_merge([
            'Content-Type' => 'application/json; charset=UTF-8',
        ], $headers));
    }

    /**
     * @param array<string, string> $headers
     */
    public static function redirect(string $location, int $status = 302, array $headers = []): self
    {
        return new self($status, '', array_merge([
            'Location' => $location,
        ], $headers));
    }

    /**
     * @param array<string, string> $headers
     */
    public static function download(string $body, string $fileName, string $contentType = 'application/octet-stream', array $headers = []): self
    {
        return new self(200, $body, array_merge([
            'Content-Type' => $contentType,
            'Content-Disposition' => sprintf('attachment; filename="%s"', $fileName),
            'Content-Length' => (string) strlen($body),
        ], $headers));
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->status, $this->body, array_merge($headers, $this->headers));
    }
}
