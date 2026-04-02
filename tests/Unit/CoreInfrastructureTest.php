<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Application;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpEmitter;
use App\Core\HttpResult;
use App\Core\Logger;
use App\Core\Validator;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

final class CoreInfrastructureTest extends TestCase
{
    public function testApplicationCachesSingletonsAndRejectsUnknownBindings(): void
    {
        $app = new Application('/tmp/example-root');
        $app->singleton(\stdClass::class, static fn (): \stdClass => new \stdClass());

        $instance = $app->get(\stdClass::class);

        self::assertSame('/tmp/example-root/path', $app->rootPath('path'));
        self::assertSame($instance, $app->get(\stdClass::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No binding registered for [DateTimeImmutable].');
        $app->get(\DateTimeImmutable::class);
    }

    public function testConfigAndValidatorHandleNestedValuesDefaultsAndAllowedLists(): void
    {
        $config = new Config([
            'app' => [
                'name' => 'SIMS',
                'debug' => false,
            ],
        ]);
        $validator = new Validator();

        [$errors, $validated] = $validator->validate([
            'name' => '  Alice  ',
            'role' => 'invalid',
            'email' => 'alice@example.test',
        ], [
            'name' => 'required',
            'role' => 'required|in:admin,student',
            'email' => ['required', 'email'],
        ]);

        self::assertSame('SIMS', $config->get('app.name'));
        self::assertSame('fallback', $config->get('app.missing', 'fallback'));
        self::assertSame(['Selected value is invalid.'], $errors['role']);
        self::assertSame('Alice', $validated['name']);
    }

    public function testDatabaseSupportsSqliteConnectionsQueriesAndFailureLogging(): void
    {
        $databaseFile = tempnam(sys_get_temp_dir(), 'sims-db-');
        self::assertNotFalse($databaseFile);
        $logFile = tempnam(sys_get_temp_dir(), 'sims-log-');
        self::assertNotFalse($logFile);

        try {
            $database = new Database(new Config([
                'db' => [
                    'driver' => 'sqlite',
                    'database' => $databaseFile,
                ],
            ]), new Logger($logFile));

            $connection = $database->connection();
            $connection->exec('CREATE TABLE sample (id INTEGER PRIMARY KEY, name TEXT)');
            $connection->exec("INSERT INTO sample (name) VALUES ('alpha')");

            self::assertSame('alpha', $database->query('SELECT name FROM sample LIMIT 1')->fetchColumn());
            self::assertSame($connection, $database->connection());

            $broken = new Database(new Config([
                'db' => [
                    'driver' => 'sqlite',
                    'database' => dirname($databaseFile) . '/missing/subdir/database.sqlite',
                ],
            ]), new Logger($logFile));

            try {
                $broken->connection();
                self::fail('Expected invalid SQLite path to fail.');
            } catch (RuntimeException $exception) {
                self::assertSame('Unable to connect to the database.', $exception->getMessage());
            }

            self::assertStringContainsString('Database connection failed.', (string) file_get_contents($logFile));
        } finally {
            @unlink($databaseFile);
            @unlink($logFile);
        }
    }

    public function testDatabaseCoversMysqlConnectionFailuresAndFalseQueryResults(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'sims-db-log-');
        self::assertNotFalse($logFile);

        try {
            $mysql = new Database(new Config([
                'db' => [
                    'driver' => 'mysql',
                    'host' => '127.0.0.1',
                    'port' => 65535,
                    'database' => 'missing_database',
                    'username' => 'missing_user',
                    'password' => 'missing_password',
                ],
            ]), new Logger($logFile));

            try {
                $mysql->connection();
                self::fail('Expected invalid MySQL configuration to fail.');
            } catch (RuntimeException $exception) {
                self::assertSame('Unable to connect to the database.', $exception->getMessage());
            }

            $sqlite = new Database(new Config([
                'db' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
            ]), new Logger($logFile));

            $connectionProperty = new ReflectionProperty(Database::class, 'connection');
            $connectionProperty->setAccessible(true);
            $connectionProperty->setValue($sqlite, new class ('sqlite::memory:') extends PDO {
                public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
                {
                    return false;
                }
            });

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Database query failed.');
            $sqlite->query('SELECT 1');
        } finally {
            @unlink($logFile);
        }
    }

    public function testHttpEmitterOutputsBodyWithoutExitingWhenTestingFlagIsEnabled(): void
    {
        $emitter = new HttpEmitter();
        $result = HttpResult::html('<p>hello</p>', 202);

        $GLOBALS['__sims_disable_exit'] = true;
        ob_start();

        try {
            $emitter->emit($result);
        } finally {
            $output = (string) ob_get_clean();
            unset($GLOBALS['__sims_disable_exit']);
        }

        self::assertSame('<p>hello</p>', $output);
        self::assertSame(202, http_response_code());
    }
}
