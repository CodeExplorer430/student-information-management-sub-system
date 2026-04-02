<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\HealthService;

final class HealthController
{
    public function __construct(
        private readonly Response $response,
        private readonly HealthService $health
    ) {
    }

    public function live(): void
    {
        $this->response->json($this->health->live());
    }

    public function ready(): void
    {
        /** @var array<string, mixed> $report */
        $report = $this->health->ready();
        $status = $report['status'] === 'pass' ? 200 : 503;

        $this->response->json($report, $status);
    }
}
