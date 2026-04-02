<?php

declare(strict_types=1);

namespace App\Core;

final class RequestContext
{
    private string $requestId = 'system';

    private string $channel = 'app';

    public function startHttp(?string $requestId = null): string
    {
        return $this->start('http', $requestId);
    }

    public function startConsole(?string $requestId = null): string
    {
        return $this->start('console', $requestId);
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function channel(): string
    {
        return $this->channel;
    }

    private function start(string $channel, ?string $requestId): string
    {
        $this->channel = $channel;
        $this->requestId = $requestId !== null && $requestId !== ''
            ? $requestId
            : bin2hex(random_bytes(8));

        return $this->requestId;
    }
}
