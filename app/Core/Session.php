<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public function __construct(
        private readonly Config $config
    ) {
        if (session_status() === PHP_SESSION_NONE) {
            $path = string_value($this->config->get('session.path', sys_get_temp_dir()), sys_get_temp_dir());
            if (!is_dir($path)) {
                mkdir($path, 0775, true);
            }

            session_save_path($path);
            session_name(string_value($this->config->get('session.name', 'simssession'), 'simssession'));
            session_set_cookie_params([
                'lifetime' => int_value($this->config->get('session.lifetime', 120), 120) * 60,
                'httponly' => true,
                'samesite' => $this->sameSitePolicy(),
                'secure' => bool_value($this->config->get('session.secure', false)),
            ]);
            session_start();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);

        return $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * @return 'Lax'|'Strict'|'None'
     */
    private function sameSitePolicy(): string
    {
        return match (strtolower(string_value($this->config->get('session.same_site', 'Lax'), 'Lax'))) {
            'strict' => 'Strict',
            'none' => 'None',
            default => 'Lax',
        };
    }
}
