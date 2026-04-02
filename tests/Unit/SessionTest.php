<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Session;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testSessionCookieParamsFollowConfiguration(): void
    {
        $path = dirname(__DIR__, 2) . '/storage/framework/test-sessions';
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        new Session(new Config([
            'session' => [
                'name' => 'securetests',
                'lifetime' => 120,
                'path' => $path,
                'same_site' => 'Strict',
                'secure' => true,
            ],
        ]));

        $params = session_get_cookie_params();

        self::assertSame('securetests', session_name());
        self::assertSame('Strict', $params['samesite']);
        self::assertTrue($params['secure']);
        self::assertTrue($params['httponly']);
    }

    #[RunInSeparateProcess]
    public function testSessionCreatesMissingDirectoryAndSupportsNoneSameSitePolicy(): void
    {
        $path = dirname(__DIR__, 2) . '/storage/framework/test-sessions-none-' . bin2hex(random_bytes(4));

        self::assertDirectoryDoesNotExist($path);

        new Session(new Config([
            'session' => [
                'name' => 'nonesession',
                'lifetime' => 60,
                'path' => $path,
                'same_site' => 'none',
                'secure' => false,
            ],
        ]));

        $params = session_get_cookie_params();

        self::assertDirectoryExists($path);
        self::assertSame('nonesession', session_name());
        self::assertSame('None', $params['samesite']);
    }
}
