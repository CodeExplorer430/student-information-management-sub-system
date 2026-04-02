<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Flash;
use App\Core\Session;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class FlashTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testAddAndPullPersistValidFlashMessages(): void
    {
        $path = dirname(__DIR__, 2) . '/storage/framework/test-flash-sessions';
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $session = new Session(new Config([
            'session' => [
                'name' => 'flash-tests',
                'lifetime' => 120,
                'path' => $path,
                'same_site' => 'Lax',
                'secure' => false,
            ],
        ]));
        $flash = new Flash($session);

        $flash->add('success', 'Saved successfully.');
        $flash->add('error', 'Something went wrong.');

        self::assertSame([
            ['type' => 'success', 'message' => 'Saved successfully.'],
            ['type' => 'error', 'message' => 'Something went wrong.'],
        ], $flash->pull());
        self::assertSame([], $flash->pull());

        $session->set('flash.messages', 'invalid-payload');

        self::assertSame([], $flash->pull());
    }
}
