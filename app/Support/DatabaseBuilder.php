<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;
use PDO;
use PDOStatement;
use RuntimeException;

final class DatabaseBuilder
{
    /**
     * @return list<string>
     */
    public static function requiredTables(): array
    {
        return [
            'users',
            'roles',
            'permissions',
            'role_permissions',
            'user_roles',
            'students',
            'student_requests',
            'request_status_histories',
            'request_notes',
            'request_attachments',
            'academic_records',
            'status_histories',
            'enrollment_status_histories',
            'id_cards',
            'audit_logs',
            'notifications',
            'notification_deliveries',
        ];
    }

    public static function migrate(PDO $database, string $driver): void
    {
        self::runSqlDirectory($database, 'migrations', $driver);
        self::applySchemaPatches($database, $driver);
    }

    public static function reset(PDO $database, string $driver): void
    {
        self::cleanupRuntimeArtifacts();

        foreach ([
            'notification_deliveries',
            'notifications',
            'request_attachments',
            'request_notes',
            'audit_logs',
            'request_status_histories',
            'student_requests',
            'id_cards',
            'enrollment_status_histories',
            'status_histories',
            'academic_records',
            'students',
            'user_roles',
            'role_permissions',
            'permissions',
            'roles',
            'users',
        ] as $table) {
            $database->exec('DROP TABLE IF EXISTS ' . $table);
        }

        self::migrate($database, $driver);
    }

    public static function seed(PDO $database, string $defaultPassword = 'Password123!'): void
    {
        $count = (int) self::query($database, 'SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            return;
        }

        self::syncDemoPortraitFixtures();

        self::runSqlDirectory(
            $database,
            'seeds',
            self::driverName($database),
            ['__DEFAULT_PASSWORD_HASH__' => password_hash($defaultPassword, PASSWORD_DEFAULT)]
        );
    }

    public static function summary(PDO $database, string $driver, string $databaseName): string
    {
        $missingTables = self::missingRequiredTables($database);
        $missingColumns = self::missingRequiredColumns($database);

        if ($missingTables !== [] || $missingColumns !== []) {
            $lines = [
                sprintf('Driver: %s', $driver),
                sprintf('Database: %s', $databaseName),
                'Schema Health: outdated',
            ];

            if ($missingTables !== []) {
                $lines[] = 'Missing tables: ' . implode(', ', $missingTables);
            }

            if ($missingColumns !== []) {
                $lines[] = 'Missing columns: ' . implode(', ', self::flattenMissingColumns($missingColumns));
            }

            $lines[] = 'Required action: run composer migrate or composer reset-db';
            $lines[] = '';

            return implode(PHP_EOL, $lines);
        }

        $userCount = (int) self::query($database, 'SELECT COUNT(*) FROM users')->fetchColumn();
        $studentCount = (int) self::query($database, 'SELECT COUNT(*) FROM students')->fetchColumn();
        $idCardCount = (int) self::query($database, 'SELECT COUNT(*) FROM id_cards')->fetchColumn();
        /** @var list<array<string, mixed>> $students */
        $students = rows_value(self::query(
            $database,
            'SELECT id, student_number, first_name, last_name, enrollment_status
             FROM students
             ORDER BY id ASC'
        )->fetchAll(PDO::FETCH_ASSOC));

        $lines = [
            sprintf('Driver: %s', $driver),
            sprintf('Database: %s', $databaseName),
            sprintf('Users: %d', $userCount),
            sprintf('Students: %d', $studentCount),
            sprintf('ID Cards: %d', $idCardCount),
            'Students:',
        ];

        if ($students === []) {
            $lines[] = '  (none)';
        } else {
            foreach ($students as $student) {
                $lines[] = sprintf(
                    '  #%d %s %s (%s) [%s]',
                    map_int($student, 'id'),
                    map_string($student, 'first_name'),
                    map_string($student, 'last_name'),
                    map_string($student, 'student_number'),
                    map_string($student, 'enrollment_status')
                );
            }
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    public static function environmentReport(PDO $database, Config $config, string $rootPath): string
    {
        $lines = self::environmentOverviewLines($config, $rootPath);

        try {
            $database->query('SELECT 1');
            $lines[] = 'Database Connectivity: ok';
            $lines[] = 'Schema Required Tables: ' . implode(', ', self::requiredTables());
            $lines[] = 'Schema Required Columns: ' . implode(', ', self::flattenMissingColumns(self::requiredColumns()));
            $missingTables = self::missingRequiredTables($database);
            $missingColumns = self::missingRequiredColumns($database);
            $lines[] = 'Schema Health: ' . ($missingTables === [] && $missingColumns === [] ? 'ok' : 'outdated');
            if ($missingTables !== []) {
                $lines[] = 'Schema Missing Tables: ' . implode(', ', $missingTables);
            }
            if ($missingColumns !== []) {
                $lines[] = 'Schema Missing Columns: ' . implode(', ', self::flattenMissingColumns($missingColumns));
            }
            if ($missingTables !== [] || $missingColumns !== []) {
                $lines[] = 'Schema Action: run composer migrate or composer reset-db';
            }
        } catch (\Throwable $exception) {
            $lines[] = 'Database Connectivity: failed - ' . $exception->getMessage();
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @return array<string, array{status: string, message: string}>
     */
    public static function deploymentReadinessStatus(Config $config, string $rootPath): array
    {
        return self::deploymentReadinessChecks($config, $rootPath);
    }

    /**
     * @return array<string, array{status: string, message: string, path: string}>
     */
    public static function directoryStatus(Config $config, string $rootPath): array
    {
        $status = [];

        foreach (self::directories($config, $rootPath) as $label => $directory) {
            $path = string_value($directory);
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $status[$label] = [
                'status' => $exists && $writable ? 'pass' : 'fail',
                'message' => $exists
                    ? ($writable ? 'Directory is present and writable.' : 'Directory is present but not writable.')
                    : 'Directory is missing.',
                'path' => $path,
            ];
        }

        return $status;
    }

    /**
     * @return array<string, array{status: string, message: string, path: string}>
     */
    public static function assetStatus(string $rootPath): array
    {
        $status = [];

        foreach (self::assets($rootPath) as $label => $assetPath) {
            $path = string_value($assetPath);
            $present = file_exists($path);
            $status[$label] = [
                'status' => $present ? 'pass' : 'fail',
                'message' => $present ? 'Asset is present.' : 'Asset is missing.',
                'path' => $path,
            ];
        }

        return $status;
    }

    public static function environmentFailureReport(
        Config $config,
        string $rootPath,
        string $databaseStatus
    ): string {
        $lines = self::environmentOverviewLines($config, $rootPath);
        $lines[] = 'Database Connectivity: ' . $databaseStatus;
        $lines[] = 'Schema Health: unknown';
        $lines[] = 'Schema Action: check MySQL service, .env credentials, then run composer migrate or composer reset-db once connectivity is restored.';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private static function syncDemoPortraitFixtures(): void
    {
        $root = dirname(__DIR__, 2);
        $sourceDirectory = $root . '/storage/app/fixtures/demo-portraits';
        $targetDirectory = $root . '/storage/app/private/uploads';

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $map = [
            'student_1' => [
                'source' => 'portrait-fin-barri.jpg',
                'target' => 'seed-student-1.jpg',
            ],
            'student_2' => [
                'source' => 'portrait-grant-allen.jpg',
                'target' => 'seed-student-2.jpg',
            ],
            'student_3' => [
                'source' => 'portrait-ansey.jpg',
                'target' => 'seed-student-3.jpg',
            ],
        ];

        foreach ($map as $key => $fixture) {
            $source = $sourceDirectory . '/' . $fixture['source'];
            $target = $targetDirectory . '/' . $fixture['target'];

            if (!file_exists($source)) {
                throw new RuntimeException(sprintf('Missing demo portrait fixture: %s', $fixture['source']));
            }

            copy($source, $target);
        }
    }

    /**
     * @return list<string>
     */
    private static function environmentOverviewLines(Config $config, string $rootPath): array
    {
        $extensions = ['gd', 'fileinfo', 'json', 'pdo_mysql', 'pdo_sqlite'];
        $lines = [
            sprintf('App URL: %s', string_value($config->get('app.url', 'http://127.0.0.1:8000'), 'http://127.0.0.1:8000')),
            sprintf('App Environment: %s', string_value($config->get('app.env', 'production'), 'production')),
            sprintf('App Debug: %s', bool_value($config->get('app.debug', false)) ? 'true' : 'false'),
            sprintf('DB Driver: %s', string_value($config->get('db.driver', 'mysql'), 'mysql')),
            sprintf('DB Database: %s', string_value($config->get('db.database', ''))),
            sprintf('Session Secure: %s', bool_value($config->get('session.secure', false)) ? 'true' : 'false'),
            'Deployment Readiness:',
        ];

        foreach (self::deploymentReadinessChecks($config, $rootPath) as $label => $details) {
            $lines[] = sprintf(
                '  - %s: %s - %s',
                $label,
                $details['status'],
                $details['message']
            );
        }

        $lines[] = 'Extensions:';
        foreach ($extensions as $extension) {
            $lines[] = sprintf('  - %s: %s', $extension, extension_loaded($extension) ? 'loaded' : 'missing');
        }

        $lines[] = 'Directories:';
        foreach (self::directoryStatus($config, $rootPath) as $label => $details) {
            $lines[] = sprintf(
                '  - %s: %s%s',
                $label,
                $details['status'] === 'pass' ? 'present' : 'missing',
                str_contains($details['message'], 'writable')
                    ? (str_contains($details['message'], 'not writable') ? ' / not writable' : ' / writable')
                    : ''
            );
        }

        $lines[] = 'Assets:';
        foreach (self::assetStatus($rootPath) as $label => $details) {
            $lines[] = sprintf('  - %s: %s', $label, $details['status'] === 'pass' ? 'present' : 'missing');
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private static function directories(Config $config, string $rootPath): array
    {
        return [
            'session_path' => string_value($config->get('session.path', $rootPath . '/storage/framework/sessions'), $rootPath . '/storage/framework/sessions'),
            'private_uploads' => $rootPath . '/storage/app/private/uploads',
            'public_id_cards' => $rootPath . '/storage/app/public/id-cards',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function assets(string $rootPath): array
    {
        return [
            'bootstrap_css' => $rootPath . '/public/assets/vendor/bootstrap/bootstrap.min.css',
            'bootstrap_js' => $rootPath . '/public/assets/vendor/bootstrap/bootstrap.bundle.min.js',
            'tabler_css' => $rootPath . '/public/assets/vendor/tabler/tabler.min.css',
            'tabler_js' => $rootPath . '/public/assets/vendor/tabler/tabler.min.js',
        ];
    }

    /**
     * @return array<string, array{status: string, message: string}>
     */
    private static function deploymentReadinessChecks(Config $config, string $rootPath): array
    {
        $appUrl = string_value($config->get('app.url', 'http://127.0.0.1:8000'), 'http://127.0.0.1:8000');
        $appEnv = string_value($config->get('app.env', 'production'), 'production');
        $appDebug = bool_value($config->get('app.debug', false));
        $appKey = string_value($config->get('app.key', 'change-me'), 'change-me');
        $sessionSecure = bool_value($config->get('session.secure', false));
        $defaultPassword = string_value($config->get('security.default_password', 'Password123!'), 'Password123!');
        $dbDriver = string_value($config->get('db.driver', 'mysql'), 'mysql');
        $emailDriver = string_value($config->get('notifications.email_driver', 'log'), 'log');
        $smsDriver = string_value($config->get('notifications.sms_driver', 'log'), 'log');
        $uploadsPath = realpath($rootPath . '/storage/app/private/uploads');
        $publicRoot = realpath($rootPath . '/public');
        $isHttps = str_starts_with($appUrl, 'https://');

        $checks = [];
        $checks['app_env'] = [
            'status' => $appEnv === 'production' ? 'pass' : 'fail',
            'message' => $appEnv === 'production'
                ? 'APP_ENV is set for a production-like deployment.'
                : sprintf('APP_ENV=%s; switch to production before shared deployment.', $appEnv),
        ];
        $checks['app_debug'] = [
            'status' => $appDebug ? 'fail' : 'pass',
            'message' => $appDebug
                ? 'APP_DEBUG is enabled; disable debug output before shared deployment.'
                : 'APP_DEBUG is disabled.',
        ];
        $checks['app_key'] = [
            'status' => $appKey === '' || $appKey === 'change-me' ? 'fail' : 'pass',
            'message' => $appKey === '' || $appKey === 'change-me'
                ? 'APP_KEY is still using the default placeholder; set a unique secret.'
                : 'APP_KEY is not using the default placeholder.',
        ];
        $checks['app_url'] = [
            'status' => $isHttps ? 'pass' : 'fail',
            'message' => $isHttps
                ? 'APP_URL is using HTTPS.'
                : 'APP_URL is not using HTTPS; update it before any HTTPS-backed shared deployment.',
        ];
        $checks['session_secure'] = [
            'status' => $sessionSecure ? 'pass' : 'fail',
            'message' => $isHttps && !$sessionSecure
                ? 'SESSION_SECURE is false while APP_URL is HTTPS.'
                : ($sessionSecure
                    ? 'SESSION_SECURE is enabled.'
                    : 'SESSION_SECURE is false; enable it before serving the app over HTTPS.'),
        ];
        $checks['db_driver'] = [
            'status' => $dbDriver === 'mysql' ? 'pass' : 'fail',
            'message' => $dbDriver === 'mysql'
                ? 'Runtime DB driver is MySQL/MariaDB.'
                : sprintf('DB_DRIVER=%s; MySQL/MariaDB remains the intended runtime target.', $dbDriver),
        ];
        $checks['default_password'] = [
            'status' => $defaultPassword === 'Password123!' ? 'fail' : 'pass',
            'message' => $defaultPassword === 'Password123!'
                ? 'DEFAULT_PASSWORD still matches the demo default; rotate credentials before shared deployment.'
                : 'DEFAULT_PASSWORD differs from the demo default.',
        ];
        $checks['email_driver'] = [
            'status' => $emailDriver === 'log' ? 'fail' : 'pass',
            'message' => $emailDriver === 'log'
                ? 'Email delivery is set to log; keep for local testing or replace for real outbound delivery.'
                : sprintf('Email delivery driver is %s.', $emailDriver),
        ];
        $checks['sms_driver'] = [
            'status' => $smsDriver === 'log' ? 'fail' : 'pass',
            'message' => $smsDriver === 'log'
                ? 'SMS delivery is set to log; keep for local testing or replace for real outbound delivery.'
                : sprintf('SMS delivery driver is %s.', $smsDriver),
        ];
        $checks['private_uploads'] = [
            'status' => self::pathIsOutsidePublicRoot($uploadsPath, $publicRoot) ? 'pass' : 'fail',
            'message' => self::pathIsOutsidePublicRoot($uploadsPath, $publicRoot)
                ? 'Private uploads resolve outside the public web root.'
                : 'Private uploads resolve inside the public web root; move them outside public/.',
        ];

        return $checks;
    }

    private static function pathIsOutsidePublicRoot(string|false|null $path, string|false|null $publicRoot): bool
    {
        if ($path === false || $path === null || $publicRoot === false || $publicRoot === null) {
            return true;
        }

        return !str_starts_with($path, rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    private static function cleanupRuntimeArtifacts(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            $root . '/storage/app/private/uploads',
            $root . '/storage/app/public/id-cards',
        ] as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $entries = scandir($directory);
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!is_string($entry)) {
                    continue;
                }

                if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
                    continue;
                }

                $path = $directory . '/' . $entry;
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * @param array<string, string> $replacements
     */
    private static function runSqlDirectory(PDO $database, string $type, string $driver, array $replacements = []): void
    {
        foreach (self::sqlFiles($type, $driver) as $file) {
            $sql = file_get_contents($file);
            if (!is_string($sql)) {
                throw new RuntimeException(sprintf('Unable to read SQL file: %s', $file));
            }

            $sql = strtr($sql, $replacements);

            foreach (self::splitStatements($sql) as $statement) {
                $database->exec($statement);
            }
        }
    }

    private static function query(PDO $database, string $sql): PDOStatement
    {
        $statement = $database->query($sql);

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Database query failed.');
        }

        return $statement;
    }

    private static function applySchemaPatches(PDO $database, string $driver): void
    {
        self::ensureColumn(
            $database,
            $driver,
            'users',
            'mobile_phone',
            $driver === 'mysql'
                ? 'ALTER TABLE users ADD COLUMN mobile_phone VARCHAR(50) DEFAULT NULL AFTER email'
                : 'ALTER TABLE users ADD COLUMN mobile_phone TEXT DEFAULT NULL'
        );

        self::ensureColumn(
            $database,
            $driver,
            'users',
            'photo_path',
            $driver === 'mysql'
                ? 'ALTER TABLE users ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL AFTER password_hash'
                : 'ALTER TABLE users ADD COLUMN photo_path TEXT DEFAULT NULL'
        );

        self::ensureColumn(
            $database,
            $driver,
            'student_requests',
            'priority',
            $driver === 'mysql'
                ? "ALTER TABLE student_requests ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'Normal' AFTER description"
                : "ALTER TABLE student_requests ADD COLUMN priority TEXT NOT NULL DEFAULT 'Normal'"
        );

        self::ensureColumn(
            $database,
            $driver,
            'student_requests',
            'due_at',
            $driver === 'mysql'
                ? 'ALTER TABLE student_requests ADD COLUMN due_at DATETIME DEFAULT NULL AFTER priority'
                : 'ALTER TABLE student_requests ADD COLUMN due_at TEXT DEFAULT NULL'
        );

        self::ensureColumn(
            $database,
            $driver,
            'student_requests',
            'resolution_summary',
            $driver === 'mysql'
                ? 'ALTER TABLE student_requests ADD COLUMN resolution_summary TEXT DEFAULT NULL AFTER resolved_at'
                : 'ALTER TABLE student_requests ADD COLUMN resolution_summary TEXT DEFAULT NULL'
        );
    }

    private static function ensureColumn(PDO $database, string $driver, string $table, string $column, string $statement): void
    {
        if (!self::tableExists($database, $table) || self::columnExists($database, $driver, $table, $column)) {
            return;
        }

        $database->exec($statement);
    }

    private static function driverName(PDO $database): string
    {
        $driver = string_value($database->getAttribute(PDO::ATTR_DRIVER_NAME));

        return $driver === 'mysql' ? 'mysql' : 'sqlite';
    }

    /**
     * @return list<string>
     */
    public static function missingRequiredTables(PDO $database): array
    {
        $existingTables = self::existingTables($database);

        return array_values(array_diff(self::requiredTables(), $existingTables));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function requiredColumns(): array
    {
        return [
            'users' => ['mobile_phone', 'photo_path'],
            'student_requests' => ['priority', 'due_at', 'resolution_summary'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function missingRequiredColumns(PDO $database): array
    {
        $driver = self::driverName($database);
        $missing = [];

        foreach (self::requiredColumns() as $table => $columns) {
            if (!self::tableExists($database, $table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!self::columnExists($database, $driver, $table, $column)) {
                    $missing[$table] ??= [];
                    $missing[$table][] = $column;
                }
            }
        }

        return $missing;
    }

    /**
     * @param array<string, list<string>> $columns
     * @return list<string>
     */
    public static function flattenMissingColumns(array $columns): array
    {
        $flattened = [];

        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column) {
                $flattened[] = sprintf('%s.%s', $table, $column);
            }
        }

        return $flattened;
    }

    /**
     * @return list<string>
     */
    public static function existingTables(PDO $database): array
    {
        $driver = self::driverName($database);
        if ($driver === 'sqlite') {
            $statement = $database->query("SELECT name FROM sqlite_master WHERE type = 'table'");
        } else {
            $statement = $database->query('SHOW TABLES');
        }

        $tables = $statement !== false ? $statement->fetchAll(PDO::FETCH_COLUMN) : [];

        return array_values(array_filter(array_map(
            static fn ($table): string => trim(string_value($table)),
            is_array($tables) ? $tables : []
        ), static fn (string $table): bool => $table !== ''));
    }

    private static function tableExists(PDO $database, string $table): bool
    {
        return in_array($table, self::existingTables($database), true);
    }

    private static function columnExists(PDO $database, string $driver, string $table, string $column): bool
    {
        if ($driver === 'sqlite') {
            $statement = $database->query(sprintf("PRAGMA table_info('%s')", str_replace("'", "''", $table)));
            $rows = $statement !== false ? rows_value($statement->fetchAll(PDO::FETCH_ASSOC)) : [];

            foreach ($rows as $row) {
                if (map_string($row, 'name') === $column) {
                    return true;
                }
            }

            return false;
        }

        $statement = $database->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = :table
             AND COLUMN_NAME = :column'
        );
        $statement->execute([
            'table' => $table,
            'column' => $column,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @return list<string>
     */
    private static function sqlFiles(string $type, string $driver): array
    {
        $root = dirname(__DIR__, 2);
        $pattern = sprintf('%s/database/%s/%s/*.sql', $root, $type, $driver);
        $files = glob($pattern);

        if ($files === false || $files === []) {
            throw new RuntimeException(sprintf('No SQL files found for %s/%s.', $type, $driver));
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private static function splitStatements(string $sql): array
    {
        $statements = preg_split('/;\s*(?:\r\n|\r|\n|$)/', trim($sql));
        if ($statements === false) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $statement): string => trim(string_value($statement)), $statements),
            static fn (string $statement): bool => $statement !== ''
        ));
    }
}
