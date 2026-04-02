<?php

declare(strict_types=1);

namespace App\Console;

final class ConsoleResult
{
    public function __construct(
        private readonly int $exitCode,
        private readonly string $stdout = '',
        private readonly string $stderr = ''
    ) {
    }

    public function exitCode(): int
    {
        return $this->exitCode;
    }

    public function stdout(): string
    {
        return $this->stdout;
    }

    public function stderr(): string
    {
        return $this->stderr;
    }
}
