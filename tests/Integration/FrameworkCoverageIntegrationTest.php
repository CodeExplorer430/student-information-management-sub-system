<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Application;
use App\Core\Auth;
use App\Core\HttpResultException;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Core\ViewContext;
use App\Repositories\UserRepository;
use LogicException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionMethod;
use RuntimeException;
use Tests\Support\IntegrationTestCase;

final class FrameworkCoverageIntegrationTest extends IntegrationTestCase
{
    public function testAuthCoversGuestInvalidAndAuthenticatedBranches(): void
    {
        $session = $this->app->get(Session::class);
        $auth = $this->app->get(Auth::class);

        self::assertFalse($auth->check());
        self::assertNull($auth->id());
        self::assertNull($auth->user());
        self::assertSame([], $auth->roles());
        self::assertSame('guest', $auth->primaryRole());
        self::assertFalse($auth->hasRole('admin'));
        self::assertFalse($auth->can('students.view'));
        self::assertFalse($auth->attempt('admin@bcp.edu', 'wrong-password'));

        self::assertTrue($auth->attempt('admin@bcp.edu', 'Password123!'));
        self::assertTrue($auth->check());
        self::assertNotNull($auth->user());
        self::assertContains('admin', $auth->roles());
        self::assertSame('admin', $auth->primaryRole());
        self::assertTrue($auth->hasRole('admin', 'student'));
        self::assertTrue($auth->can('students.view'));

        $auth->logout();

        self::assertNull($session->get('auth.user_id'));
        self::assertFalse($auth->check());
    }

    public function testResponseCoversViewRedirectBackAndDownloadBranches(): void
    {
        $response = $this->app->get(Response::class);
        $downloadFile = tempnam(sys_get_temp_dir(), 'sims-download-');
        self::assertNotFalse($downloadFile);
        file_put_contents($downloadFile, 'binary-body');

        try {
            $viewResult = $this->captureResult(static fn () => $response->view('partials/404', [], 404));
            self::assertSame(404, $viewResult->status());
            self::assertStringContainsString('Resource not found', $viewResult->body());

            $redirectResult = $this->captureResult(static fn () => $response->redirect('/target', 'Saved.'));
            self::assertSame('/target', $redirectResult->headers()['Location'] ?? null);
            $flashMessages = $this->app->get(Session::class)->get('flash.messages');
            self::assertIsArray($flashMessages);
            self::assertIsArray($flashMessages[0] ?? null);
            self::assertSame('Saved.', $flashMessages[0]['message'] ?? null);

            $_POST['_back'] = 'https://example.test/requests/7?tab=history';
            $backResult = $this->captureResult(static fn () => $response->back('/fallback', 'Returned.'));
            self::assertSame('/requests/7?tab=history', $backResult->headers()['Location'] ?? null);

            $_POST['_back'] = [];
            $_SERVER['HTTP_REFERER'] = '';
            $fallbackBack = $this->captureResult(static fn () => $response->back('/fallback'));
            self::assertSame('/fallback', $fallbackBack->headers()['Location'] ?? null);

            $_POST['_back'] = 'https://example.test?tab=history';
            $queryOnlyBack = $this->captureResult(static fn () => $response->back('/fallback', 'Returned again.'));
            self::assertSame('/fallback?tab=history', $queryOnlyBack->headers()['Location'] ?? null);

            $downloadResult = $this->captureResult(static fn () => $response->download($downloadFile, 'example.txt', 'text/plain'));
            self::assertSame('text/plain', $downloadResult->headers()['Content-Type'] ?? null);
            self::assertSame('attachment; filename="example.txt"', $downloadResult->headers()['Content-Disposition'] ?? null);
            self::assertSame('binary-body', $downloadResult->body());

            $unsafeNameDownload = $this->captureResult(static fn () => $response->download($downloadFile, '../bad"' . "\r\n" . '.txt', 'text/plain'));
            self::assertSame('attachment; filename="bad_.txt"', $unsafeNameDownload->headers()['Content-Disposition'] ?? null);

            $missingDownload = $this->captureResult(static fn () => $response->download('/definitely/missing/file.txt', 'missing.txt'));
            self::assertSame('', $missingDownload->body());

            $inlineDownload = $this->captureResult(static fn () => $response->downloadContent('inline', 'content.txt', 'text/plain'));
            self::assertSame('inline', $inlineDownload->body());
        } finally {
            unset($_POST['_back'], $_SERVER['HTTP_REFERER']);
            @unlink($downloadFile);
        }
    }

    public function testRouterMiddlewareBranchesCoverRedirectPaths(): void
    {
        $router = $this->app->get(Router::class);
        $runMiddleware = new ReflectionMethod(Router::class, 'runMiddleware');
        $runMiddleware->setAccessible(true);

        $_SESSION = [];

        try {
            $runMiddleware->invoke($router, ['auth']);
            self::fail('Expected auth middleware to redirect guests.');
        } catch (HttpResultException $exception) {
            self::assertSame('/login', $exception->result()->headers()['Location'] ?? null);
            /** @var array<string, mixed> $sessionData */
            $sessionData = $_SESSION;
            self::assertSame('Please sign in first.', $sessionData['auth.login_notice'] ?? null);
        }

        $student = $this->app->get(UserRepository::class)->findByEmail('student@bcp.edu');
        self::assertNotNull($student);
        $_SESSION['auth.user_id'] = (int) $student['id'];

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf'] = 'invalid-token';

        try {
            $runMiddleware->invoke($router, ['csrf']);
            self::fail('Expected csrf middleware to redirect on invalid tokens.');
        } catch (HttpResultException $exception) {
            self::assertSame('/dashboard', $exception->result()->headers()['Location'] ?? null);
        }

        try {
            $runMiddleware->invoke($router, ['role:admin']);
            self::fail('Expected role middleware to deny student users.');
        } catch (HttpResultException $exception) {
            self::assertSame('/dashboard', $exception->result()->headers()['Location'] ?? null);
        }

        try {
            $runMiddleware->invoke($router, ['permission:admin.users']);
            self::fail('Expected permission middleware to deny student users.');
        } catch (HttpResultException $exception) {
            self::assertSame('/dashboard', $exception->result()->headers()['Location'] ?? null);
        }

        $runMiddleware->invoke($router, ['permission:admin.users,requests.view_own']);
    }

    public function testViewAndViewContextCoverLayoutPartialAndErrorBranches(): void
    {
        $viewDirectory = sys_get_temp_dir() . '/sims-view-' . bin2hex(random_bytes(4));
        mkdir($viewDirectory, 0775, true);

        file_put_contents($viewDirectory . '/plain.php', <<<'PHP'
<?php
echo 'plain:' . e($name) . '|old:' . e($old['name'] ?? '') . '|flash:' . e(count($flashMessages)) . '|path:' . e($current_path);
PHP);
        file_put_contents($viewDirectory . '/partial.php', <<<'PHP'
<?php
echo 'partial:' . e($label);
PHP);
        file_put_contents($viewDirectory . '/layout.php', <<<'PHP'
<?php
echo 'layout:' . e($title) . '[' . $view->section('content') . ']';
PHP);
        file_put_contents($viewDirectory . '/page.php', <<<'PHP'
<?php $view->layout('layout', ['title' => 'Wrapped']); ?>
<?php $view->start('content'); ?>
<?= $view->renderPartial('partial', ['label' => $name]) ?>
<?php $view->end(); ?>
PHP);
        file_put_contents($viewDirectory . '/page-auto.php', <<<'PHP'
<?php $view->layout('layout', ['title' => 'Auto Wrapped']); ?>
auto:<?= e($name) ?>
PHP);

        $_SERVER['REQUEST_URI'] = '/rendered/path?x=1';
        $session = $this->app->get(Session::class);
        $session->set('_old', ['name' => 'From session']);
        $session->set('flash.messages', [
            ['type' => 'success', 'message' => 'Saved.'],
            'skip-me',
        ]);

        $view = new View(
            $viewDirectory,
            $session,
            $this->app->get(\App\Core\Csrf::class),
            $this->app->get(Auth::class),
            $this->app->get(\App\Core\Config::class),
            $this->app->get(\App\Repositories\NotificationRepository::class)
        );

        $plain = $view->render('plain', ['name' => 'Alice']);
        $wrapped = $view->render('page', ['name' => 'Alice']);
        $autoWrapped = $view->render('page-auto', ['name' => 'Alice']);
        $session->set('flash.messages', 'invalid');
        $invalidFlash = $view->render('plain', ['name' => 'Bob']);

        self::assertSame('plain:Alice|old:From session|flash:1|path:/rendered/path', $plain);
        self::assertSame('layout:Wrapped[partial:Alice]', trim($wrapped));
        self::assertSame('layout:Auto Wrapped[auto:Alice]', trim($autoWrapped));
        self::assertSame('plain:Bob|old:|flash:0|path:/rendered/path', $invalidFlash);
        self::assertSame([], $session->get('flash.messages', []));
        self::assertNull($session->get('_old'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('View template [missing-template] was not found.');
        $view->renderPartial('missing-template');
    }

    public function testHttpKernelThrowsWhenRouterCompletesWithoutProducingAResponse(): void
    {
        $app = new Application(sys_get_temp_dir());
        $database = $this->app->get(\App\Core\Database::class)->connection();

        $app->singleton(\App\Core\Database::class, static fn (): object => new class ($database) {
            public function __construct(private readonly \PDO $database)
            {
            }

            public function connection(): \PDO
            {
                return $this->database;
            }
        });
        $app->singleton(\App\Core\Config::class, static fn (): \App\Core\Config => new \App\Core\Config([
            'db' => ['database' => 'coverage.sqlite'],
        ]));
        $app->singleton(\App\Core\RequestContext::class, static fn (): \App\Core\RequestContext => new \App\Core\RequestContext());
        $app->singleton(\App\Core\Logger::class, static fn (\App\Core\Application $app): \App\Core\Logger => new \App\Core\Logger(
            $app->rootPath('coverage-http-kernel.log'),
            $app->get(\App\Core\RequestContext::class)
        ));
        $app->singleton(\App\Core\Router::class, static fn (): object => new class () {
            public function dispatch(string $method, string $uri): void
            {
            }
        });

        $kernel = new \App\Core\HttpKernel($app);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Request completed without producing a response.');
        $kernel->handle('GET', '/no-response');
    }

    public function testHttpKernelReturnsStructuredInternalErrorWhenRouterThrowsUnexpectedException(): void
    {
        $app = new Application(sys_get_temp_dir());
        $database = $this->app->get(\App\Core\Database::class)->connection();

        $app->singleton(\App\Core\Database::class, static fn (): object => new class ($database) {
            public function __construct(private readonly \PDO $database)
            {
            }

            public function connection(): \PDO
            {
                return $this->database;
            }
        });
        $app->singleton(\App\Core\Config::class, static fn (): \App\Core\Config => new \App\Core\Config([
            'db' => ['database' => 'coverage.sqlite'],
        ]));
        $app->singleton(\App\Core\RequestContext::class, static fn (): \App\Core\RequestContext => new \App\Core\RequestContext());
        $app->singleton(\App\Core\Logger::class, static fn (\App\Core\Application $app): \App\Core\Logger => new \App\Core\Logger(
            $app->rootPath('coverage-http-kernel-error.log'),
            $app->get(\App\Core\RequestContext::class)
        ));
        $app->singleton(\App\Core\Router::class, static fn (): object => new class () {
            public function dispatch(string $method, string $uri): never
            {
                throw new RuntimeException('simulated router crash');
            }
        });

        $kernel = new \App\Core\HttpKernel($app);
        $result = $kernel->handle('GET', '/explode');

        self::assertSame(500, $result->status());
        self::assertArrayHasKey('X-Request-Id', $result->headers());
        self::assertStringContainsString('Application error', $result->body());
        self::assertStringContainsString('Request ID:', $result->body());
    }

    public function testViewContextCoversCaptureSectionsAndLogicExceptions(): void
    {
        $viewDirectory = sys_get_temp_dir() . '/sims-view-context-' . bin2hex(random_bytes(4));
        mkdir($viewDirectory, 0775, true);
        file_put_contents($viewDirectory . '/blank.php', '');

        $view = new View(
            $viewDirectory,
            $this->app->get(Session::class),
            $this->app->get(\App\Core\Csrf::class),
            $this->app->get(Auth::class),
            $this->app->get(\App\Core\Config::class),
            $this->app->get(\App\Repositories\NotificationRepository::class)
        );

        $context = new ViewContext($view);
        $context->setData(['name' => 'Alice']);
        $context->layout('blank', ['title' => 'Layout']);

        self::assertSame(['name' => 'Alice'], $context->allData());
        self::assertSame('blank', $context->layoutTemplate());
        self::assertSame(['title' => 'Layout'], $context->layoutData());
        self::assertSame('captured', $context->capture(static function (): void {
            echo 'captured';
        }));

        $context->start('content');
        echo 'section-body';
        $context->end();

        self::assertTrue($context->hasSection('content'));
        self::assertSame('section-body', $context->section('content'));
        $context->setSection('footer', 'footer-body');
        self::assertSame('footer-body', $context->section('footer'));
        self::assertSame('fallback', $context->section('missing', 'fallback'));
        self::assertSame('', $context->renderPartial('blank'));

        try {
            $context->end();
            self::fail('Expected end() without active section to fail.');
        } catch (LogicException $exception) {
            self::assertSame('No section is currently being captured.', $exception->getMessage());
        }

        $context->start('first');

        try {
            $context->start('second');
            self::fail('Expected nested start() to fail.');
        } catch (LogicException $exception) {
            self::assertSame('A section is already being captured.', $exception->getMessage());
        } finally {
            ob_end_clean();
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConsoleEntrypointWritesIntoInjectedStreamsAndReturnsExitCode(): void
    {
        $stdout = fopen('php://temp', 'w+');
        $stderr = fopen('php://temp', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $argv = [dirname(__DIR__, 2) . '/bin/console', 'env:check'];
        $GLOBALS['__sims_disable_exit'] = true;
        $GLOBALS['__sims_stdout'] = $stdout;
        $GLOBALS['__sims_stderr'] = $stderr;

        try {
            /** @var int $exitCode */
            $exitCode = require dirname(__DIR__, 2) . '/bin/console';
        } finally {
            unset($GLOBALS['__sims_disable_exit'], $GLOBALS['__sims_stdout'], $GLOBALS['__sims_stderr']);
        }

        rewind($stdout);
        rewind($stderr);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Deployment Readiness:', (string) stream_get_contents($stdout));
        self::assertSame('', (string) stream_get_contents($stderr));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testConsoleEntrypointCanDispatchThroughTheExitHandlerHook(): void
    {
        $stdout = fopen('php://temp', 'w+');
        $stderr = fopen('php://temp', 'w+');
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        $argv = [dirname(__DIR__, 2) . '/bin/console', 'env:check'];
        $GLOBALS['__sims_stdout'] = $stdout;
        $GLOBALS['__sims_stderr'] = $stderr;
        $GLOBALS['__sims_console_exit_handler'] = static function (int $exitCode): void {
            $GLOBALS['__sims_console_exit_code'] = $exitCode;
        };

        require dirname(__DIR__, 2) . '/bin/console';

        rewind($stdout);
        rewind($stderr);

        self::assertSame(0, $GLOBALS['__sims_console_exit_code'] ?? null);
        self::assertStringContainsString('Deployment Readiness:', (string) stream_get_contents($stdout));
        self::assertSame('', (string) stream_get_contents($stderr));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPublicEntrypointRendersResponseWhenExitIsDisabled(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/login';
        $_SERVER['HTTP_HOST'] = '127.0.0.1';
        $_SERVER['SERVER_NAME'] = '127.0.0.1';
        $_SERVER['SERVER_PORT'] = '8000';

        $GLOBALS['__sims_disable_exit'] = true;
        ob_start();

        try {
            require dirname(__DIR__, 2) . '/public/index.php';
        } finally {
            $output = (string) ob_get_clean();
            unset($GLOBALS['__sims_disable_exit']);
        }

        self::assertStringContainsString('Secure access portal', $output);
    }

    private function captureResult(callable $callback): \App\Core\HttpResult
    {
        try {
            $callback();
        } catch (HttpResultException $exception) {
            return $exception->result();
        }

        self::fail('Expected the callback to throw an HttpResultException.');
    }
}
