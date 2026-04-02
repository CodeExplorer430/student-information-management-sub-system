<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Application;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\HttpResult;
use App\Core\HttpResultException;
use App\Core\Logger;
use App\Core\Router;
use App\Core\Session;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

final class RouterDispatchProbe
{
    /**
     * @var list<mixed>
     */
    public array $arguments = [];

    public function handle(int $id, float $ratio, bool $enabled, string $name, mixed $extra = null): void
    {
        $this->arguments = [$id, $ratio, $enabled, $name, $extra];
    }
}

final class RouterResultProbe
{
    public function respond(): void
    {
        throw new HttpResultException(HttpResult::html('probe'));
    }
}

final class RouterInvocationProbe
{
    /**
     * @var array<string, string>
     */
    public array $payload = [];

    public object|null $objectPayload = null;

    public \DateTimeImmutable|null $datePayload = null;

    /**
     * @param array<string, string> $payload
     */
    public function acceptArray(array $payload): void
    {
        $this->payload = $payload;
    }

    public function acceptObject(object $payload): void
    {
        $this->objectPayload = $payload;
    }

    public function acceptDate(\DateTimeImmutable $payload): void
    {
        $this->datePayload = $payload;
    }
}

final class RouterTest extends TestCase
{
    public function testRedirectCanBeExercisedWithoutProcessExitInTests(): void
    {
        $router = new Router(new Application(sys_get_temp_dir()));
        $GLOBALS['__sims_disable_exit'] = true;

        try {
            $router->redirect('/next');
        } finally {
            unset($GLOBALS['__sims_disable_exit']);
        }

        self::assertSame(302, http_response_code());
    }

    public function testDispatchCastsRouteParametersToBuiltinControllerTypes(): void
    {
        $app = $this->routerApplication();
        $probe = new RouterDispatchProbe();
        $app->singleton(RouterDispatchProbe::class, static fn (): RouterDispatchProbe => $probe);

        $router = new Router($app);
        $router->get('/typed/[*:id]/[*:ratio]/[*:enabled]/[*:name]', [RouterDispatchProbe::class, 'handle']);

        $router->dispatch('get', '/typed/7/1.5/true/Aira');

        self::assertSame([7, 1.5, true, 'Aira', null], $probe->arguments);
    }

    public function testDispatchAttachesSecurityHeadersToThrownHttpResults(): void
    {
        $app = $this->routerApplication();
        $probe = new RouterResultProbe();
        $app->singleton(RouterResultProbe::class, static fn (): RouterResultProbe => $probe);

        $router = new Router($app);
        $router->get('/result', [RouterResultProbe::class, 'respond']);

        try {
            $router->dispatch('GET', '/result');
            self::fail('Expected router dispatch to rethrow an HttpResultException.');
        } catch (HttpResultException $exception) {
            self::assertSame('probe', $exception->result()->body());
            self::assertSame('DENY', $exception->result()->headers()['X-Frame-Options'] ?? null);
            self::assertArrayHasKey('Content-Security-Policy', $exception->result()->headers());
        }
    }

    public function testInvokeControllerCoversBuiltinDefaultAndNonBuiltinPaths(): void
    {
        $router = new Router($this->routerApplication());
        $probe = new RouterInvocationProbe();
        $invoke = new \ReflectionMethod(Router::class, 'invokeController');
        $invoke->setAccessible(true);

        $objectPayload = new \stdClass();
        $invoke->invoke($router, $probe, 'acceptArray', [['key' => 'value']]);
        $invoke->invoke($router, $probe, 'acceptObject', [$objectPayload]);
        $invoke->invoke($router, $probe, 'acceptDate', [new \DateTimeImmutable('2026-04-01 00:00:00')]);

        self::assertSame(['key' => 'value'], $probe->payload);
        self::assertSame($objectPayload, $probe->objectPayload);
        self::assertInstanceOf(\DateTimeImmutable::class, $probe->datePayload);
    }

    private function routerApplication(): Application
    {
        $app = new Application(sys_get_temp_dir());
        $logFile = tempnam(sys_get_temp_dir(), 'router-log-');
        self::assertNotFalse($logFile);

        $app->singleton(Config::class, static fn (): Config => new Config([
            'db' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
            'session' => [
                'path' => sys_get_temp_dir(),
                'name' => 'routertestsession',
                'lifetime' => 120,
                'same_site' => 'Lax',
                'secure' => false,
            ],
        ]));
        $app->singleton(Logger::class, static fn (): Logger => new Logger($logFile));
        $app->singleton(Database::class, static fn (Application $app): Database => new Database(
            $app->get(Config::class),
            $app->get(Logger::class)
        ));
        $app->singleton(Session::class, static fn (Application $app): Session => new Session($app->get(Config::class)));
        $app->singleton(Csrf::class, static fn (Application $app): Csrf => new Csrf($app->get(Session::class)));
        $app->singleton(UserRepository::class, static fn (Application $app): UserRepository => new UserRepository($app->get(Database::class)));
        $app->singleton(RoleRepository::class, static fn (Application $app): RoleRepository => new RoleRepository($app->get(Database::class)));
        $app->singleton(Auth::class, static fn (Application $app): Auth => new Auth(
            $app->get(Session::class),
            $app->get(UserRepository::class),
            $app->get(RoleRepository::class)
        ));

        return $app;
    }
}
