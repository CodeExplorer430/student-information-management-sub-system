<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Console\ConsoleResult;
use PHPUnit\Framework\TestCase;

final class ConsoleResultTest extends TestCase
{
    public function testConsoleResultReturnsConfiguredStreamsAndExitCode(): void
    {
        $result = new ConsoleResult(2, "stdout\n", "stderr\n");

        self::assertSame(2, $result->exitCode());
        self::assertSame("stdout\n", $result->stdout());
        self::assertSame("stderr\n", $result->stderr());
    }
}
