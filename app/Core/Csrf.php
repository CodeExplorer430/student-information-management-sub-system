<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Csrf
{
    private const TOKEN_KEY = '_csrf_token';

    public function __construct(
        private readonly Session $session
    ) {
    }

    public function token(): string
    {
        $token = $this->session->get(self::TOKEN_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::TOKEN_KEY, $token);
        }

        return $token;
    }

    public function validate(?string $token): void
    {
        if (!is_string($token) || !hash_equals($this->token(), $token)) {
            throw new RuntimeException('Invalid CSRF token.');
        }
    }
}
