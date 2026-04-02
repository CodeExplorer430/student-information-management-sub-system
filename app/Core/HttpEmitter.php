<?php

declare(strict_types=1);

namespace App\Core;

final class HttpEmitter
{
    public function emit(HttpResult $result): void
    {
        http_response_code($result->status());

        foreach ($result->headers() as $name => $value) {
            header($name . ': ' . $value, true);
        }

        echo $result->body();
    }
}
