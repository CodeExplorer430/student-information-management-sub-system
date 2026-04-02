<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\RequestContext;
use App\Support\DatabaseBuilder;
use Throwable;

final class HealthService
{
    public function __construct(
        private readonly Database $database,
        private readonly Config $config,
        private readonly RequestContext $requestContext,
        private readonly string $rootPath
    ) {
    }

    /**
     * @return HealthReport
     */
    public function live(): array
    {
        /** @var list<HealthCheck> $checks */
        $checks = [[
            'name' => 'application',
            'status' => 'pass',
            'message' => 'HTTP kernel is responsive.',
        ]];

        return $this->report('pass', $checks);
    }

    /**
     * @return HealthReport
     */
    public function ready(): array
    {
        /** @var list<HealthCheck> $checks */
        $checks = [];

        try {
            $database = $this->database->connection();
            $database->query('SELECT 1');
            $checks[] = [
                'name' => 'database_connectivity',
                'status' => 'pass',
                'message' => 'Database connection is available.',
            ];

            $missingTables = DatabaseBuilder::missingRequiredTables($database);
            $checks[] = [
                'name' => 'schema_required_tables',
                'status' => $missingTables === [] ? 'pass' : 'fail',
                'message' => $missingTables === []
                    ? 'All required tables are present.'
                    : 'Missing tables: ' . implode(', ', $missingTables),
            ];
        } catch (Throwable $exception) {
            $checks[] = [
                'name' => 'database_connectivity',
                'status' => 'fail',
                'message' => 'Database connection failed: ' . $exception->getMessage(),
            ];
            $checks[] = [
                'name' => 'schema_required_tables',
                'status' => 'fail',
                'message' => 'Schema health is unknown until database connectivity is restored.',
            ];
        }

        foreach (DatabaseBuilder::directoryStatus($this->config, $this->rootPath) as $name => $details) {
            $checks[] = [
                'name' => $name,
                'status' => $details['status'],
                'message' => $details['message'],
            ];
        }

        foreach (DatabaseBuilder::assetStatus($this->rootPath) as $name => $details) {
            $checks[] = [
                'name' => $name,
                'status' => $details['status'],
                'message' => $details['message'],
            ];
        }

        $status = 'pass';
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $status = 'fail';
                break;
            }
        }

        return $this->report($status, $checks);
    }

    /**
     * @return array<string, array{status: string, message: string}>
     */
    public function deploymentReadiness(): array
    {
        return DatabaseBuilder::deploymentReadinessStatus($this->config, $this->rootPath);
    }

    /**
     * @return array<string, array{status: string, message: string, path: string}>
     */
    public function directories(): array
    {
        return DatabaseBuilder::directoryStatus($this->config, $this->rootPath);
    }

    /**
     * @return array<string, array{status: string, message: string, path: string}>
     */
    public function assets(): array
    {
        return DatabaseBuilder::assetStatus($this->rootPath);
    }

    /**
     * @param list<HealthCheck> $checks
     * @return HealthReport
     */
    private function report(string $status, array $checks): array
    {
        $version = string_value($this->config->get('app.version', ''));

        return [
            'status' => $status,
            'timestamp' => date('c'),
            'request_id' => $this->requestContext->requestId(),
            'checks' => $checks,
            'version' => $version !== '' ? $version : null,
        ];
    }
}
