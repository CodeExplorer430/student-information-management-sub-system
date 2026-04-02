<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Csrf;
use App\Core\HttpKernel;
use App\Core\HttpResult;
use App\Core\Session;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;

abstract class HttpIntegrationTestCase extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->get(Session::class);
        $_SESSION = [];
        $this->resetRequestState();
    }

    protected function tearDown(): void
    {
        $this->resetRequestState();
        $_SESSION = [];

        parent::tearDown();
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @param array<string, scalar|null> $server
     */
    protected function request(
        string $method,
        string $path,
        array $query = [],
        array $post = [],
        array $files = [],
        array $server = []
    ): HttpResult {
        $queryString = http_build_query(array_filter($query, static fn (mixed $value): bool => $value !== null));
        $requestUri = $path . ($queryString !== '' ? '?' . $queryString : '');

        $this->resetRequestState();
        $_GET = map_value($query);
        $_POST = map_value($post);
        $_FILES = map_value($files);
        $_SERVER = array_merge($_SERVER, map_value($server), [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $requestUri,
            'HTTP_HOST' => '127.0.0.1',
            'SERVER_NAME' => '127.0.0.1',
            'SERVER_PORT' => '8000',
            'HTTPS' => '',
        ]);

        return $this->app->get(HttpKernel::class)->handle(
            string_value($_SERVER['REQUEST_METHOD'] ?? strtoupper($method), strtoupper($method)),
            string_value($_SERVER['REQUEST_URI'] ?? $requestUri, $requestUri)
        );
    }

    /**
     * @return UserRow
     */
    protected function actingAs(string $email): array
    {
        $this->app->get(Session::class);

        $user = $this->app->get(UserRepository::class)->findByEmail($email);

        self::assertNotNull($user, sprintf('Expected seeded user for [%s].', $email));

        $_SESSION['auth.user_id'] = (int) $user['id'];

        return $user;
    }

    protected function csrfToken(): string
    {
        return $this->app->get(Csrf::class)->token();
    }

    /**
     * @return StudentRow
     */
    protected function studentForEmail(string $email): array
    {
        $student = $this->app->get(StudentRepository::class)->findByEmail($email);

        self::assertNotNull($student, sprintf('Expected seeded student for [%s].', $email));

        return $student;
    }

    protected function assertRedirect(HttpResult $result, string $location, int $status = 302): void
    {
        self::assertSame($status, $result->status());
        self::assertSame($location, $result->headers()['Location'] ?? null);
    }

    protected function assertHtml(HttpResult $result, int $status = 200): void
    {
        self::assertSame($status, $result->status());
        self::assertSame('text/html; charset=UTF-8', $result->headers()['Content-Type'] ?? null);
        self::assertArrayHasKey('Content-Security-Policy', $result->headers());
    }

    private function resetRequestState(): void
    {
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        unset(
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            $_SERVER['HTTP_REFERER'],
            $_SERVER['HTTP_HOST'],
            $_SERVER['SERVER_NAME'],
            $_SERVER['SERVER_PORT'],
            $_SERVER['HTTPS']
        );
    }
}
