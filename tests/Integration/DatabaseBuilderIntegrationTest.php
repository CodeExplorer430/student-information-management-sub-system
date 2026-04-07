<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\Database;
use App\Support\DatabaseBuilder;
use PDO;
use ReflectionMethod;
use Tests\Support\IntegrationTestCase;

final class DatabaseBuilderIntegrationTest extends IntegrationTestCase
{
    public function testMigratedTestDatabaseHasNoMissingRequiredTables(): void
    {
        $database = $this->app->get(Database::class)->connection();

        self::assertSame([], DatabaseBuilder::missingRequiredTables($database));
    }

    public function testMissingRequiredTablesReportsOutdatedSchema(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT)');
        $database->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, slug TEXT)');

        $missingTables = DatabaseBuilder::missingRequiredTables($database);

        self::assertContains('user_roles', $missingTables);
        self::assertContains('students', $missingTables);
    }

    public function testEnvironmentReportListsMissingTablesForOutdatedSchema(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT)');
        $database->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, slug TEXT)');

        $report = DatabaseBuilder::environmentReport(
            $database,
            $this->app->get(Config::class),
            dirname(__DIR__, 2)
        );

        self::assertStringContainsString('Schema Health: outdated', $report);
        self::assertStringContainsString('Schema Missing Tables:', $report);
        self::assertStringContainsString('user_roles', $report);
    }

    public function testEnvironmentReportListsMissingColumnsForOutdatedSchema(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        foreach (DatabaseBuilder::requiredTables() as $table) {
            $database->exec(sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY)', $table));
        }

        $report = DatabaseBuilder::environmentReport(
            $database,
            $this->app->get(Config::class),
            dirname(__DIR__, 2)
        );

        self::assertStringContainsString('Schema Health: outdated', $report);
        self::assertStringContainsString('Schema Missing Columns:', $report);
        self::assertStringContainsString('users.photo_path', $report);
        self::assertStringContainsString('student_requests.priority', $report);
    }

    public function testEnvironmentReportFlagsDeploymentReadinessFailuresForDefaultLikeSettings(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        DatabaseBuilder::migrate($database, 'sqlite');

        $config = new Config([
            'app' => [
                'url' => 'http://127.0.0.1:8000',
                'env' => 'development',
                'debug' => true,
                'key' => 'change-me',
            ],
            'db' => [
                'driver' => 'sqlite',
                'database' => 'test.sqlite',
            ],
            'session' => [
                'secure' => false,
                'path' => dirname(__DIR__, 2) . '/storage/framework/sessions',
            ],
            'security' => [
                'default_password' => 'Password123!',
            ],
            'notifications' => [
                'email_driver' => 'log',
                'sms_driver' => 'log',
            ],
        ]);

        $report = DatabaseBuilder::environmentReport($database, $config, dirname(__DIR__, 2));

        self::assertStringContainsString('Deployment Readiness:', $report);
        self::assertStringContainsString('app_env: fail', $report);
        self::assertStringContainsString('app_debug: fail', $report);
        self::assertStringContainsString('app_key: fail', $report);
        self::assertStringContainsString('app_url: fail', $report);
        self::assertStringContainsString('session_secure: fail', $report);
        self::assertStringContainsString('db_driver: fail', $report);
        self::assertStringContainsString('default_password: fail', $report);
        self::assertStringContainsString('email_driver: fail', $report);
        self::assertStringContainsString('sms_driver: fail', $report);
        self::assertStringContainsString('private_uploads: pass', $report);
    }

    public function testEnvironmentReportMarksProductionReadySettingsAsPass(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        DatabaseBuilder::migrate($database, 'sqlite');

        $config = new Config([
            'app' => [
                'url' => 'https://sis.example.test',
                'env' => 'production',
                'debug' => false,
                'key' => 'replace-with-real-secret',
            ],
            'db' => [
                'driver' => 'mysql',
                'database' => 'student_information_management',
            ],
            'session' => [
                'secure' => true,
                'path' => dirname(__DIR__, 2) . '/storage/framework/sessions',
            ],
            'security' => [
                'default_password' => 'RotateMe123!@#',
            ],
            'notifications' => [
                'email_driver' => 'mail',
                'sms_driver' => 'http',
            ],
        ]);

        $report = DatabaseBuilder::environmentReport($database, $config, dirname(__DIR__, 2));

        self::assertStringContainsString('app_env: pass', $report);
        self::assertStringContainsString('app_debug: pass', $report);
        self::assertStringContainsString('app_key: pass', $report);
        self::assertStringContainsString('app_url: pass', $report);
        self::assertStringContainsString('session_secure: pass', $report);
        self::assertStringContainsString('db_driver: pass', $report);
        self::assertStringContainsString('default_password: pass', $report);
        self::assertStringContainsString('email_driver: pass', $report);
        self::assertStringContainsString('sms_driver: pass', $report);
        self::assertStringContainsString('private_uploads: pass', $report);
    }

    public function testEnvironmentFailureReportStillIncludesDeploymentReadinessGuidance(): void
    {
        $config = new Config([
            'app' => [
                'url' => 'https://sis.example.test',
                'env' => 'production',
                'debug' => false,
                'key' => 'replace-with-real-secret',
            ],
            'db' => [
                'driver' => 'mysql',
                'database' => 'student_information_management',
            ],
            'session' => [
                'secure' => false,
                'path' => dirname(__DIR__, 2) . '/storage/framework/sessions',
            ],
            'security' => [
                'default_password' => 'Password123!',
            ],
            'notifications' => [
                'email_driver' => 'log',
                'sms_driver' => 'log',
            ],
        ]);

        $report = DatabaseBuilder::environmentFailureReport(
            $config,
            dirname(__DIR__, 2),
            'failed - could not connect'
        );

        self::assertStringContainsString('Database Connectivity: failed - could not connect', $report);
        self::assertStringContainsString('Schema Health: unknown', $report);
        self::assertStringContainsString('session_secure: fail', $report);
        self::assertStringContainsString('default_password: fail', $report);
    }

    public function testMigrateBackfillsWorkflowColumnsOntoOlderSchema(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password_hash TEXT, role TEXT, department TEXT, created_at TEXT, updated_at TEXT)');
        $database->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, slug TEXT, name TEXT, description TEXT, created_at TEXT, updated_at TEXT)');
        $database->exec('CREATE TABLE permissions (id INTEGER PRIMARY KEY, code TEXT, label TEXT, module TEXT, description TEXT, created_at TEXT, updated_at TEXT)');
        $database->exec('CREATE TABLE role_permissions (role_id INTEGER, permission_id INTEGER)');
        $database->exec('CREATE TABLE students (id INTEGER PRIMARY KEY, student_number TEXT, first_name TEXT, middle_name TEXT, last_name TEXT, birthdate TEXT, program TEXT, year_level TEXT, email TEXT, phone TEXT, address TEXT, guardian_name TEXT, guardian_contact TEXT, department TEXT, enrollment_status TEXT, photo_path TEXT, created_at TEXT, updated_at TEXT)');
        $database->exec('CREATE TABLE student_requests (id INTEGER PRIMARY KEY, student_id INTEGER, request_type TEXT, title TEXT, description TEXT, status TEXT, assigned_user_id INTEGER, created_by_user_id INTEGER, submitted_at TEXT, updated_at TEXT, resolved_at TEXT)');
        $database->exec('CREATE TABLE request_status_histories (id INTEGER PRIMARY KEY, request_id INTEGER, status TEXT, remarks TEXT, assigned_user_id INTEGER, created_at TEXT)');
        $database->exec('CREATE TABLE academic_records (id INTEGER PRIMARY KEY, student_id INTEGER, term_label TEXT, subject_code TEXT, subject_title TEXT, units NUMERIC, grade TEXT, created_at TEXT)');
        $database->exec('CREATE TABLE status_histories (id INTEGER PRIMARY KEY, student_id INTEGER, status TEXT, remarks TEXT, assigned_user_id INTEGER, created_at TEXT)');
        $database->exec('CREATE TABLE enrollment_status_histories (id INTEGER PRIMARY KEY, student_id INTEGER, status TEXT, remarks TEXT, assigned_user_id INTEGER, created_at TEXT)');
        $database->exec('CREATE TABLE id_cards (id INTEGER PRIMARY KEY, student_id INTEGER, file_path TEXT, qr_payload TEXT, barcode_payload TEXT, generated_by INTEGER, generated_at TEXT)');
        $database->exec('CREATE TABLE audit_logs (id INTEGER PRIMARY KEY, user_id INTEGER, entity_type TEXT, entity_id INTEGER, action TEXT, old_values TEXT, new_values TEXT, created_at TEXT)');
        $database->exec('CREATE TABLE user_roles (user_id INTEGER, role_id INTEGER, created_at TEXT)');

        DatabaseBuilder::migrate($database, 'sqlite');

        self::assertSame([], DatabaseBuilder::missingRequiredTables($database));
        self::assertTrue($this->sqliteColumnExists($database, 'users', 'mobile_phone'));
        self::assertTrue($this->sqliteColumnExists($database, 'users', 'photo_path'));
        self::assertTrue($this->sqliteColumnExists($database, 'student_requests', 'priority'));
        self::assertTrue($this->sqliteColumnExists($database, 'student_requests', 'due_at'));
        self::assertTrue($this->sqliteColumnExists($database, 'student_requests', 'resolution_summary'));
    }

    public function testMissingRequiredColumnsReportsSchemaDriftForExistingTables(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        foreach (DatabaseBuilder::requiredTables() as $table) {
            $database->exec(sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY)', $table));
        }

        $missingColumns = DatabaseBuilder::missingRequiredColumns($database);

        self::assertSame(['mobile_phone', 'photo_path'], $missingColumns['users'] ?? []);
        self::assertSame(['priority', 'due_at', 'resolution_summary'], $missingColumns['student_requests'] ?? []);
        self::assertContains('users.photo_path', DatabaseBuilder::flattenMissingColumns($missingColumns));
    }

    public function testSummaryAndResetCoverHealthyAndOutdatedBranches(): void
    {
        $database = $this->app->get(Database::class)->connection();

        $healthySummary = DatabaseBuilder::summary($database, 'sqlite', 'test-db');

        self::assertStringContainsString('Users: 5', $healthySummary);
        self::assertStringContainsString('Students:', $healthySummary);

        $outdatedDatabase = new PDO('sqlite::memory:');
        $outdatedDatabase->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $outdatedDatabase->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, role TEXT)');

        $outdatedSummary = DatabaseBuilder::summary($outdatedDatabase, 'sqlite', 'outdated');

        self::assertStringContainsString('Schema Health: outdated', $outdatedSummary);
        self::assertStringContainsString('Required action: run composer migrate or composer reset-db', $outdatedSummary);

        $columnDriftDatabase = new PDO('sqlite::memory:');
        $columnDriftDatabase->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (DatabaseBuilder::requiredTables() as $table) {
            $columnDriftDatabase->exec(sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY)', $table));
        }

        $columnDriftSummary = DatabaseBuilder::summary($columnDriftDatabase, 'sqlite', 'column-drift');

        self::assertStringContainsString('Schema Health: outdated', $columnDriftSummary);
        self::assertStringContainsString('Missing columns: users.mobile_phone, users.photo_path', $columnDriftSummary);

        $uploadFile = dirname(__DIR__, 2) . '/storage/app/private/uploads/test-reset.txt';
        $cardFile = dirname(__DIR__, 2) . '/storage/app/public/id-cards/test-reset.txt';
        file_put_contents($uploadFile, 'temp');
        file_put_contents($cardFile, 'temp');

        DatabaseBuilder::reset($database, 'sqlite');

        self::assertFileDoesNotExist($uploadFile);
        self::assertFileDoesNotExist($cardFile);
        self::assertSame([], DatabaseBuilder::missingRequiredTables($database));
    }

    public function testPrivateHelpersCoverSqlDiscoveryPathChecksAndQueryFailures(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE sample (id INTEGER PRIMARY KEY, name TEXT)');

        $splitStatements = new ReflectionMethod(DatabaseBuilder::class, 'splitStatements');
        $splitStatements->setAccessible(true);
        $driverName = new ReflectionMethod(DatabaseBuilder::class, 'driverName');
        $driverName->setAccessible(true);
        $query = new ReflectionMethod(DatabaseBuilder::class, 'query');
        $query->setAccessible(true);
        $sqlFiles = new ReflectionMethod(DatabaseBuilder::class, 'sqlFiles');
        $sqlFiles->setAccessible(true);
        $pathOutside = new ReflectionMethod(DatabaseBuilder::class, 'pathIsOutsidePublicRoot');
        $pathOutside->setAccessible(true);
        $columnExists = new ReflectionMethod(DatabaseBuilder::class, 'columnExists');
        $columnExists->setAccessible(true);

        self::assertSame(
            ['CREATE TABLE one (id INTEGER PRIMARY KEY)', 'INSERT INTO one (id) VALUES (1)'],
            $splitStatements->invoke(null, "CREATE TABLE one (id INTEGER PRIMARY KEY);\nINSERT INTO one (id) VALUES (1);\n")
        );
        self::assertSame([], $splitStatements->invoke(null, "   \n  "));
        self::assertSame('sqlite', $driverName->invoke(null, $database));
        $migrationFiles = $sqlFiles->invoke(null, 'migrations', 'sqlite');
        self::assertIsArray($migrationFiles);
        self::assertContains(
            dirname(__DIR__, 2) . '/database/migrations/sqlite/001_create_schema.sql',
            $migrationFiles
        );
        self::assertFalse($pathOutside->invoke(
            null,
            dirname(__DIR__, 2) . '/public/uploads',
            dirname(__DIR__, 2) . '/public'
        ));
        self::assertTrue($pathOutside->invoke(null, null, dirname(__DIR__, 2) . '/public'));
        $queryResult = $query->invoke(null, $database, 'SELECT name FROM sqlite_master WHERE type = "table" LIMIT 1');
        self::assertInstanceOf(\PDOStatement::class, $queryResult);
        self::assertSame('sample', $queryResult->fetchColumn());
        self::assertTrue($columnExists->invoke(null, $database, 'sqlite', 'sample', 'name'));
        self::assertFalse($columnExists->invoke(null, $database, 'sqlite', 'sample', 'missing_column'));

        $database->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $database->sqliteCreateFunction('DATABASE', static fn (): string => 'main');
        $database->exec('CREATE TABLE information_schema.COLUMNS (TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
        $database->exec("INSERT INTO information_schema.COLUMNS (TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME) VALUES ('main', 'sample', 'name')");
        self::assertTrue($columnExists->invoke(null, $database, 'mysql', 'sample', 'name'));
        self::assertFalse($columnExists->invoke(null, $database, 'mysql', 'sample', 'missing_column'));

        try {
            $query->invoke(null, $database, 'SELECT * FROM definitely_missing_table');
            self::fail('Expected invalid SQL query to fail.');
        } catch (\ReflectionException) {
            self::fail('Unexpected reflection failure.');
        } catch (\Throwable $exception) {
            self::assertStringContainsString('no such table', $exception->getMessage());
        }

        try {
            $sqlFiles->invoke(null, 'missing-dir', 'sqlite');
            self::fail('Expected missing SQL directory failure.');
        } catch (\Throwable $exception) {
            self::assertStringContainsString('No SQL files found', $exception->getMessage());
        }

        $falseQueryPdo = new class ('sqlite::memory:') extends PDO {
            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
            {
                return false;
            }
        };

        try {
            $query->invoke(null, $falseQueryPdo, 'SELECT 1');
            self::fail('Expected false-returning query branch to fail.');
        } catch (\Throwable $exception) {
            self::assertStringContainsString('Database query failed.', $exception->getMessage());
        }
    }

    private function sqliteColumnExists(PDO $database, string $table, string $column): bool
    {
        $statement = $database->query(sprintf("PRAGMA table_info('%s')", $table));
        $rows = $statement !== false ? rows_value($statement->fetchAll(PDO::FETCH_ASSOC)) : [];

        foreach ($rows as $row) {
            if (map_string($row, 'name') === $column) {
                return true;
            }
        }

        return false;
    }
}
