<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;

final class Auth
{
    private const USER_SESSION_KEY = 'auth.user_id';

    public function __construct(
        private readonly Session $session,
        private readonly UserRepository $users,
        private readonly RoleRepository $roles
    ) {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $this->session->regenerate();
        $this->session->set(self::USER_SESSION_KEY, (int) $user['id']);

        return true;
    }

    public function logout(): void
    {
        $this->session->forget(self::USER_SESSION_KEY);
        $this->session->regenerate();
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    public function id(): ?int
    {
        $value = $this->session->get(self::USER_SESSION_KEY);

        return is_int($value) ? $value : null;
    }

    /**
     * @return UserRow|null
     */
    public function user(): ?array
    {
        $id = $this->id();

        return $id !== null ? $this->users->find($id) : null;
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        $user = $this->user();
        if ($user === null) {
            return [];
        }

        return array_values(array_filter($user['roles'], static fn (string $role): bool => $role !== ''));
    }

    public function primaryRole(): string
    {
        $user = $this->user();

        return (string) ($user['role'] ?? 'guest');
    }

    public function hasRole(string ...$roles): bool
    {
        return array_intersect($this->roles(), $roles) !== [];
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return in_array($permission, $this->permissions(), true);
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        $user = $this->user();
        if ($user === null) {
            return [];
        }

        return $this->roles->permissionsForRoles($this->roles());
    }
}
