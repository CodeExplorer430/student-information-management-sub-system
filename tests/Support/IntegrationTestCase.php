<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Application;
use App\Core\Config;
use App\Core\Database;
use App\Support\DatabaseBuilder;
use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected Application $app;
    protected string $databaseFile;
    protected string $backupStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $databaseDirectory = dirname(__DIR__, 2) . '/storage/database';
        if (!is_dir($databaseDirectory)) {
            mkdir($databaseDirectory, 0775, true);
        }

        $this->databaseFile = $databaseDirectory . '/test-' . getmypid() . '-' . md5(static::class . '::' . $this->name()) . '.sqlite';
        if (file_exists($this->databaseFile)) {
            unlink($this->databaseFile);
        }

        touch($this->databaseFile);
        $_ENV['DB_DATABASE'] = $this->databaseFile;
        $_SERVER['DB_DATABASE'] = $this->databaseFile;

        $this->backupStoragePath = sys_get_temp_dir() . '/sims-backups-' . getmypid() . '-' . md5(static::class . '::' . $this->name());
        if (is_dir($this->backupStoragePath)) {
            $this->removeDirectory($this->backupStoragePath);
        }

        mkdir($this->backupStoragePath, 0775, true);
        $_ENV['BACKUP_STORAGE_PATH'] = $this->backupStoragePath;
        $_SERVER['BACKUP_STORAGE_PATH'] = $this->backupStoragePath;

        /** @var Application $app */
        $app = require dirname(__DIR__, 2) . '/config/app.php';
        $this->app = $app;
        $_SESSION = [];

        $database = $app->get(Database::class)->connection();
        $config = $app->get(Config::class);
        DatabaseBuilder::reset($database, string_value($config->get('db.driver', 'sqlite'), 'sqlite'));
        DatabaseBuilder::seed($database, string_value($config->get('security.default_password', 'Password123!'), 'Password123!'));
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        if (isset($this->databaseFile) && file_exists($this->databaseFile)) {
            @unlink($this->databaseFile);
        }

        if (isset($this->backupStoragePath) && is_dir($this->backupStoragePath)) {
            $this->removeDirectory($this->backupStoragePath);
        }

        unset($_ENV['BACKUP_STORAGE_PATH'], $_SERVER['BACKUP_STORAGE_PATH']);

        parent::tearDown();
    }

    private function removeDirectory(string $directory): void
    {
        $entries = scandir($directory);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
