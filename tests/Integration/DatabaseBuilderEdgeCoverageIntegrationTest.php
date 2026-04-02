<?php

declare(strict_types=1);

namespace App\Support {
    /**
     * @param 0|1|2 $sortingOrder
     * @return list<mixed>|false
     */
    function scandir(string $directory, int $sortingOrder = SCANDIR_SORT_ASCENDING)
    {
        $targets = $GLOBALS['__sims_support_scandir_false'] ?? [];
        $overrides = $GLOBALS['__sims_support_scandir_entries'] ?? [];

        if (is_array($targets) && in_array($directory, $targets, true)) {
            return false;
        }

        if (is_array($overrides) && array_key_exists($directory, $overrides) && is_array($overrides[$directory])) {
            return array_values($overrides[$directory]);
        }

        if (!in_array($sortingOrder, [SCANDIR_SORT_ASCENDING, SCANDIR_SORT_DESCENDING, SCANDIR_SORT_NONE], true)) {
            $sortingOrder = SCANDIR_SORT_ASCENDING;
        }

        return \scandir($directory, $sortingOrder);
    }

    function file_get_contents(string $filename): string|false
    {
        $targets = $GLOBALS['__sims_support_file_contents_false'] ?? [];

        if (is_array($targets) && in_array($filename, $targets, true)) {
            return false;
        }

        return \file_get_contents($filename);
    }

    /**
     * @return list<string>|false
     */
    function preg_split(string $pattern, string $subject, int $limit = -1, int $flags = 0): array|false
    {
        if (($GLOBALS['__sims_support_preg_split_false'] ?? false) === true) {
            return false;
        }

        $parts = \preg_split($pattern, $subject, $limit, $flags);
        if ($parts === false) {
            return false;
        }

        $normalized = [];

        foreach ($parts as $part) {
            if (is_string($part)) {
                $normalized[] = $part;
            }
        }

        return $normalized;
    }
}

namespace Tests\Integration {

    use App\Core\Config;
    use App\Support\DatabaseBuilder;
    use PDO;
    use ReflectionMethod;
    use RuntimeException;
    use Tests\Support\IntegrationTestCase;

    final class DatabaseBuilderEdgeCoverageIntegrationTest extends IntegrationTestCase
    {
        public function testSummaryCanReportNoStudents(): void
        {
            $database = new PDO('sqlite::memory:');
            $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            DatabaseBuilder::migrate($database, 'sqlite');

            $summary = DatabaseBuilder::summary($database, 'sqlite', 'empty-db');

            self::assertStringContainsString('Students: 0', $summary);
            self::assertStringContainsString('  (none)', $summary);
        }

        public function testEnvironmentReportCanCoverConnectivityFailureCatch(): void
        {
            $database = new class ('sqlite::memory:') extends PDO {
                public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
                {
                    throw new RuntimeException('forced failure');
                }
            };

            $report = DatabaseBuilder::environmentReport(
                $database,
                new Config(['app' => ['url' => 'https://sis.example.test']]),
                dirname(__DIR__, 2)
            );

            self::assertStringContainsString('Database Connectivity: failed - forced failure', $report);
        }

        public function testPrivateHelpersCoverFixtureMkdirMissingFixtureCleanupAndSqlFailureBranches(): void
        {
            $syncDemoPortraitFixtures = new ReflectionMethod(DatabaseBuilder::class, 'syncDemoPortraitFixtures');
            $syncDemoPortraitFixtures->setAccessible(true);
            $cleanupRuntimeArtifacts = new ReflectionMethod(DatabaseBuilder::class, 'cleanupRuntimeArtifacts');
            $cleanupRuntimeArtifacts->setAccessible(true);
            $runSqlDirectory = new ReflectionMethod(DatabaseBuilder::class, 'runSqlDirectory');
            $runSqlDirectory->setAccessible(true);
            $splitStatements = new ReflectionMethod(DatabaseBuilder::class, 'splitStatements');
            $splitStatements->setAccessible(true);

            $root = dirname(__DIR__, 2);
            $uploadsDirectory = $root . '/storage/app/private/uploads';
            $uploadsBackup = $root . '/storage/app/private/uploads-backup-' . bin2hex(random_bytes(4));
            $fixtureFile = $root . '/storage/app/fixtures/demo-portraits/portrait-fin-barri.jpg';
            $fixtureBackup = $fixtureFile . '.bak';

            rename($uploadsDirectory, $uploadsBackup);

            try {
                $syncDemoPortraitFixtures->invoke(null);
                self::assertFileExists($uploadsDirectory . '/seed-student-1.jpg');
            } finally {
                $this->removeDirectoryFiles($uploadsDirectory);
                @rmdir($uploadsDirectory);
                rename($uploadsBackup, $uploadsDirectory);
            }

            rename($fixtureFile, $fixtureBackup);

            try {
                $syncDemoPortraitFixtures->invoke(null);
                self::fail('Expected missing portrait fixture failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Missing demo portrait fixture', $exception->getMessage());
            } finally {
                rename($fixtureBackup, $fixtureFile);
            }

            $cardsDirectory = $root . '/storage/app/public/id-cards';
            $cardsBackup = $root . '/storage/app/public/id-cards-backup-' . bin2hex(random_bytes(4));
            rename($cardsDirectory, $cardsBackup);

            try {
                $cleanupRuntimeArtifacts->invoke(null);
            } finally {
                rename($cardsBackup, $cardsDirectory);
            }

            $GLOBALS['__sims_support_scandir_false'] = [$uploadsDirectory];

            try {
                $cleanupRuntimeArtifacts->invoke(null);
            } finally {
                unset($GLOBALS['__sims_support_scandir_false']);
            }

            $GLOBALS['__sims_support_scandir_entries'] = [
                $uploadsDirectory => ['.', '..', 123, '.gitkeep'],
            ];

            try {
                $cleanupRuntimeArtifacts->invoke(null);
            } finally {
                unset($GLOBALS['__sims_support_scandir_entries']);
            }

            $database = new PDO('sqlite::memory:');
            $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $firstMigrationFile = dirname(__DIR__, 2) . '/database/migrations/sqlite/001_create_schema.sql';
            $GLOBALS['__sims_support_file_contents_false'] = [$firstMigrationFile];

            try {
                $runSqlDirectory->invoke(null, $database, 'migrations', 'sqlite');
                self::fail('Expected SQL file read failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('Unable to read SQL file', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_support_file_contents_false']);
            }

            $GLOBALS['__sims_support_preg_split_false'] = true;

            try {
                self::assertSame([], $splitStatements->invoke(null, 'SELECT 1;'));
            } finally {
                unset($GLOBALS['__sims_support_preg_split_false']);
            }
        }

        public function testMysqlLikeHelpersCoverShowTablesAndPatchStatements(): void
        {
            $existingTables = new ReflectionMethod(DatabaseBuilder::class, 'existingTables');
            $existingTables->setAccessible(true);
            $applySchemaPatches = new ReflectionMethod(DatabaseBuilder::class, 'applySchemaPatches');
            $applySchemaPatches->setAccessible(true);

            $database = new class ('sqlite::memory:') extends PDO {
                private PDO $helper;

                /** @var list<string> */
                public array $statements = [];

                public function __construct(string $dsn)
                {
                    parent::__construct($dsn);
                    $this->helper = new PDO('sqlite::memory:');
                }

                public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
                {
                    if ($query === 'SHOW TABLES') {
                        return $this->helper->query("SELECT 'users' UNION ALL SELECT 'student_requests'");
                    }

                    return parent::query($query, $fetchMode, ...$fetchModeArgs);
                }

                public function getAttribute(int $attribute): mixed
                {
                    if ($attribute === \PDO::ATTR_DRIVER_NAME) {
                        return 'mysql';
                    }

                    return parent::getAttribute($attribute);
                }

                /**
                 * @param array<int|string, mixed> $options
                 */
                public function prepare(string $query, array $options = []): \PDOStatement|false
                {
                    if (str_contains($query, 'information_schema.COLUMNS')) {
                        return $this->helper->prepare('SELECT 0 WHERE :table IS NOT NULL AND :column IS NOT NULL');
                    }

                    return parent::prepare($query, $options);
                }

                public function exec(string $statement): int
                {
                    $this->statements[] = $statement;

                    return 0;
                }
            };

            $tables = $existingTables->invoke(null, $database);
            $applySchemaPatches->invoke(null, $database, 'mysql');

            self::assertSame(['users', 'student_requests'], $tables);
            self::assertContains('ALTER TABLE users ADD COLUMN mobile_phone VARCHAR(50) DEFAULT NULL AFTER email', $database->statements);
            self::assertContains("ALTER TABLE student_requests ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'Normal' AFTER description", $database->statements);
            self::assertContains('ALTER TABLE student_requests ADD COLUMN due_at DATETIME DEFAULT NULL AFTER priority', $database->statements);
            self::assertContains('ALTER TABLE student_requests ADD COLUMN resolution_summary TEXT DEFAULT NULL AFTER resolved_at', $database->statements);
        }

        private function removeDirectoryFiles(string $directory): void
        {
            $entries = \scandir($directory);

            if (!is_array($entries)) {
                return;
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $directory . '/' . $entry;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }
}
