<?php

declare(strict_types=1);

namespace App\Core;

use AltoRouter;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

final class Router
{
    private AltoRouter $router;

    public function __construct(
        private readonly Application $app
    ) {
        $this->router = new AltoRouter();
    }

    /**
     * @param array{0: class-string, 1: string} $target
     * @param list<string> $middleware
     */
    public function get(string $route, array $target, array $middleware = []): void
    {
        $this->map('GET', $route, $target, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string} $target
     * @param list<string> $middleware
     */
    public function post(string $route, array $target, array $middleware = []): void
    {
        $this->map('POST', $route, $target, $middleware);
    }

    public function redirect(string $path): void
    {
        header('Location: ' . $path, true, 302);
    }

    public function dispatch(string $method, string $uri): void
    {
        try {
            $match = $this->router->match(strtok($uri, '?') ?: '/', $this->normalizeMethod($method));

            if ($match === false) {
                $this->app->get(Response::class)->view('partials/404', [], 404);
            }

            /** @var array{target: array{controller: array{0: class-string, 1: string}, middleware: list<string>}, params: array<string, scalar|null>} $match */
            $this->runMiddleware($match['target']['middleware']);

            [$controllerClass, $action] = $match['target']['controller'];
            $controller = $this->app->get($controllerClass);
            $params = array_values($match['params']);

            $this->invokeController($controller, $action, $params);
        } catch (HttpResultException $exception) {
            throw $exception->withHeaders($this->securityHeaders());
        }
    }

    /**
     * @param array{0: class-string, 1: string} $target
     * @param list<string> $middleware
     */
    private function map(string $method, string $route, array $target, array $middleware): void
    {
        $this->router->map($method, $route, [
            'controller' => $target,
            'middleware' => $middleware,
        ]);
    }

    private function normalizeMethod(string $method): string
    {
        return strtoupper($method);
    }

    /**
     * @return array<string, string>
     */
    private function securityHeaders(): array
    {
        return [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; form-action 'self'; object-src 'none'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; font-src 'self' data:;",
        ];
    }

    /**
     * @param list<string> $middleware
     */
    private function runMiddleware(array $middleware): void
    {
        $auth = $this->app->get(Auth::class);
        $csrf = $this->app->get(Csrf::class);
        $session = $this->app->get(Session::class);

        foreach ($middleware as $entry) {
            if ($entry === 'auth' && !$auth->check()) {
                $session->set('auth.login_notice', 'Please sign in first.');
                $this->app->get(Response::class)->redirect('/login');
            }

            if ($entry === 'csrf' && string_value($_SERVER['REQUEST_METHOD'] ?? 'GET', 'GET') === 'POST') {
                try {
                    $csrf->validate(nullable_string_value($_POST['_csrf'] ?? null));
                } catch (RuntimeException) {
                    $this->app->get(Response::class)->back('/dashboard', 'Your session security token is invalid or expired. Please try again.', 'error');
                }
            }

            if (str_starts_with($entry, 'role:')) {
                $roles = explode(',', substr($entry, 5));

                if (!$auth->hasRole(...$roles)) {
                    $this->app->get(Response::class)->redirect('/dashboard', 'You are not authorized to access this resource.', 'error');
                }
            }

            if (str_starts_with($entry, 'permission:')) {
                $permissions = array_values(array_filter(array_map('trim', explode(',', substr($entry, 11)))));
                $authorized = false;

                foreach ($permissions as $permission) {
                    if ($auth->can($permission)) {
                        $authorized = true;
                        break;
                    }
                }

                if (!$authorized) {
                    $this->app->get(Response::class)->redirect('/dashboard', 'You are not authorized to access this resource.', 'error');
                }
            }
        }
    }

    /**
     * @param list<scalar|null> $params
     */
    private function invokeController(object $controller, string $action, array $params): void
    {
        $method = new ReflectionMethod($controller, $action);
        $arguments = [];

        foreach ($method->getParameters() as $index => $parameter) {
            $value = $params[$index] ?? null;
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin() === false) {
                $arguments[] = $value;
                continue;
            }

            $arguments[] = match ($type->getName()) {
                'int' => (int) $value,
                'float' => (float) $value,
                'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                'string' => (string) $value,
                default => $value,
            };
        }

        $method->invokeArgs($controller, $arguments);
    }
}
