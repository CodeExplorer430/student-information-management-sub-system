<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use PHPUnit\Framework\Assert;
use Tests\AcceptanceTester;

final class SecurityHeadersCest
{
    public function loginPageEmitsSecurityHeadersAndSessionCookie(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');

        $appUrl = rtrim(env('APP_URL', 'http://127.0.0.1:18081') ?? 'http://127.0.0.1:18081', '/');
        $headers = get_headers($appUrl . '/login', true);

        Assert::assertIsArray($headers);
        Assert::assertSame('DENY', $headers['X-Frame-Options'] ?? null);
        Assert::assertSame('nosniff', $headers['X-Content-Type-Options'] ?? null);
        Assert::assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy'] ?? null);
        Assert::assertSame('geolocation=(), microphone=(), camera=()', $headers['Permissions-Policy'] ?? null);
        Assert::assertNotEmpty($headers['X-Request-Id'] ?? null);

        $contentSecurityPolicy = $headers['Content-Security-Policy'] ?? '';
        if (is_array($contentSecurityPolicy)) {
            $contentSecurityPolicy = implode("\n", $contentSecurityPolicy);
        }

        Assert::assertStringContainsString("default-src 'self'", $contentSecurityPolicy);
        Assert::assertStringContainsString("font-src 'self' data:", $contentSecurityPolicy);

        $setCookie = $headers['Set-Cookie'] ?? '';
        if (is_array($setCookie)) {
            $setCookie = implode("\n", $setCookie);
        }

        Assert::assertStringContainsString('simse2esession=', (string) $setCookie);
        Assert::assertStringContainsString('HttpOnly', (string) $setCookie);
        Assert::assertStringContainsString('SameSite=Lax', (string) $setCookie);
    }
}
