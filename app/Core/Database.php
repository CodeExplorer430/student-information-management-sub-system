<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger
    ) {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $driver = string_value($this->config->get('db.driver', 'mysql'), 'mysql');
        $database = string_value($this->config->get('db.database', ''));

        try {
            if ($driver === 'sqlite') {
                $dsn = sprintf('sqlite:%s', $database);
                $this->connection = new PDO($dsn);
            } else {
                $dsn = sprintf(
                    '%s:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $driver,
                    string_value($this->config->get('db.host', '127.0.0.1'), '127.0.0.1'),
                    int_value($this->config->get('db.port', 3306), 3306),
                    $database
                );
                $this->connection = new PDO(
                    $dsn,
                    string_value($this->config->get('db.username', '')),
                    string_value($this->config->get('db.password', ''))
                );
            }
        } catch (PDOException $exception) {
            $this->logger->error('Database connection failed.', [
                'driver' => $driver,
                'database' => $database,
                'message' => $exception->getMessage(),
            ], 'database');

            throw new RuntimeException('Unable to connect to the database.', 0, $exception);
        }

        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if ($driver === 'sqlite') {
            $this->connection->exec('PRAGMA foreign_keys = ON;');
        }

        return $this->connection;
    }

    public function query(string $sql): PDOStatement
    {
        $statement = $this->connection()->query($sql);

        if (!$statement instanceof PDOStatement) {
            throw new RuntimeException('Database query failed.');
        }

        return $statement;
    }
}
