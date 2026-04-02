<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpResultException extends RuntimeException
{
    public function __construct(
        private readonly HttpResult $result
    ) {
        parent::__construct('HTTP response generated.');
    }

    public function result(): HttpResult
    {
        return $this->result;
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->result->withHeaders($headers));
    }
}
