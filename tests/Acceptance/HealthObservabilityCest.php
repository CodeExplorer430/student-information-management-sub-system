<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use PHPUnit\Framework\Assert;
use Tests\AcceptanceTester;

final class HealthObservabilityCest
{
    public function healthEndpointsExposeMachineReadableRuntimeStatus(AcceptanceTester $I): void
    {
        $I->amOnPage('/health/live');
        $I->seeResponseCodeIsSuccessful();

        $appUrl = rtrim(env('APP_URL', 'http://127.0.0.1:18081') ?? 'http://127.0.0.1:18081', '/');
        $liveResponse = file_get_contents($appUrl . '/health/live');
        $readyHeaders = get_headers($appUrl . '/health/ready', true);
        $readyResponse = file_get_contents($appUrl . '/health/ready');

        Assert::assertIsString($liveResponse);
        $livePayload = json_decode($liveResponse, true);
        Assert::assertIsArray($livePayload);
        Assert::assertSame('pass', $livePayload['status'] ?? null);
        Assert::assertNotEmpty($livePayload['request_id'] ?? null);

        Assert::assertIsArray($readyHeaders);
        Assert::assertSame('application/json; charset=UTF-8', $readyHeaders['Content-Type'] ?? null);
        Assert::assertArrayHasKey('X-Request-Id', $readyHeaders);

        Assert::assertIsString($readyResponse);
        $readyPayload = json_decode($readyResponse, true);
        Assert::assertIsArray($readyPayload);
        Assert::assertSame('pass', $readyPayload['status'] ?? null);
        Assert::assertIsArray($readyPayload['checks'] ?? null);
    }
}
