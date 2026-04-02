<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\ReportService;
use Tests\Support\IntegrationTestCase;

final class ReportServiceIntegrationTest extends IntegrationTestCase
{
    public function testOverviewAndExportsReturnSeededData(): void
    {
        $service = $this->app->get(ReportService::class);

        $overview = $service->overview([]);

        self::assertSame(3, $overview['studentCount']);
        self::assertSame(5, $overview['userCount']);
        self::assertNotEmpty($overview['requestRows']);
        self::assertNotEmpty($overview['auditRows']);
        self::assertNotEmpty($overview['notificationRows']);

        $studentRows = $service->exportRows('students', []);
        $requestRows = $service->exportRows('requests', []);
        $notificationRows = $service->exportRows('notifications', []);

        self::assertNotEmpty($studentRows);
        self::assertNotEmpty($requestRows);
        self::assertNotEmpty($notificationRows);
        self::assertArrayHasKey('student_number', $studentRows[0]);
        self::assertArrayHasKey('title', $requestRows[0]);
        self::assertArrayHasKey('recipient', $notificationRows[0]);
    }
}
