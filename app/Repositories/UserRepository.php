<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UserRepository
{
    private const PRIMARY_ROLE_PRIORITY = [
        'admin',
        'registrar',
        'staff',
        'faculty',
        'student',
    ];

    public function __construct(
        private readonly Database $database
    ) {
    }

    /**
     * @return UserRow|null
     */
    public function find(int $id): ?array
    {
        $statement = $this->database->connection()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        if (!is_array($user)) {
            return null;
        }

        /** @var UserRow $user */
        return $this->hydrateUser($user);
    }

    /**
     * @return UserRow|null
     */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->database->connection()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if (!is_array($user)) {
            return null;
        }

        /** @var UserRow $user */
        return $this->hydrateUser($user);
    }

    public function count(): int
    {
        return (int) $this->database->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    /**
     * @param array{name:string, email:string, password_hash:string, role?:string, roles?: list<string>, department:string, created_at:string, updated_at:string} $data
     */
    public function create(array $data): int
    {
        $roles = $this->normalizeRoles($data['roles'] ?? [$data['role'] ?? '']);
        $primaryRole = $roles[0] ?? (string) ($data['role'] ?? '');
        $data['role'] = $primaryRole;
        unset($data['roles']);

        $statement = $this->database->connection()->prepare(
            'INSERT INTO users (name, email, password_hash, role, department, created_at, updated_at)
             VALUES (:name, :email, :password_hash, :role, :department, :created_at, :updated_at)'
        );

        $statement->execute($data);

        $userId = (int) $this->database->connection()->lastInsertId();
        $this->syncRoles($userId, $roles !== [] ? $roles : [$primaryRole]);

        return $userId;
    }

    /**
     * @return list<UserRow>
     */
    public function all(): array
    {
        /** @var list<UserRow> $users */
        $users = rows_value($this->database->query('SELECT id, name, email, mobile_phone, role, department FROM users ORDER BY name ASC')
            ->fetchAll(PDO::FETCH_ASSOC));

        if ($users === []) {
            return [];
        }

        $roleIds = array_values(array_map(static fn (array $user): int => map_int($user, 'id'), $users));
        $roleMap = $this->rolesForUserIds($roleIds);

        foreach ($users as &$user) {
            $userRoles = $roleMap[map_int($user, 'id')] ?? [];
            $user['roles'] = $userRoles !== [] ? $userRoles : [map_string($user, 'role')];
        }
        unset($user);

        return $users;
    }

    /**
     * @param list<int> $ids
     * @return list<UserRow>
     */
    public function findManyByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $statement = $this->database->connection()->prepare(
            'SELECT id, name, email, mobile_phone, role, department
             FROM users
             WHERE id IN (' . $placeholders . ')
             ORDER BY name ASC'
        );
        $statement->execute($ids);
        /** @var list<UserRow> $users */
        $users = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));
        $roleMap = $this->rolesForUserIds($ids);

        foreach ($users as &$user) {
            $user['roles'] = $roleMap[map_int($user, 'id')] ?? [map_string($user, 'role')];
        }
        unset($user);

        return $users;
    }

    public function updateRole(int $id, string $role): void
    {
        $this->syncRoles($id, [$role]);
    }

    /**
     * @param list<string> $roles
     */
    public function updateRoles(int $id, array $roles): void
    {
        $this->syncRoles($id, $roles);
    }

    /**
     * @param UserRow $user
     * @return UserRow
     */
    private function hydrateUser(array $user): array
    {
        $userId = map_int($user, 'id');
        $roles = $this->rolesForUserIds([$userId]);
        $user['roles'] = $roles[$userId] ?? [map_string($user, 'role')];

        return $user;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, list<string>>
     */
    private function rolesForUserIds(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds, static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($userIds), '?'));
        $statement = $this->database->connection()->prepare(
            'SELECT user_roles.user_id, roles.slug
             FROM user_roles
             INNER JOIN roles ON roles.id = user_roles.role_id
             WHERE user_roles.user_id IN (' . $placeholders . ')'
        );
        $statement->execute($userIds);
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        $map = [];
        foreach ($rows as $row) {
            $userId = map_int($row, 'user_id');
            $slug = map_string($row, 'slug');
            if ($userId <= 0 || $slug === '') {
                continue;
            }
            $map[$userId] ??= [];
            $map[$userId][] = $slug;
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function normalizeRoles(mixed $roles): array
    {
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($role): string => trim(string_value($role)),
            $roles
        ), static fn (string $role): bool => $role !== '')));
    }

    /**
     * @param list<string> $roles
     */
    private function syncRoles(int $id, array $roles): void
    {
        $roles = $this->normalizeRoles($roles);
        if ($roles === []) {
            return;
        }

        $connection = $this->database->connection();
        $connection->beginTransaction();

        $delete = $connection->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
        $delete->execute(['user_id' => $id]);

        $lookup = $connection->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $insert = $connection->prepare(
            'INSERT INTO user_roles (user_id, role_id, created_at) VALUES (:user_id, :role_id, :created_at)'
        );

        foreach ($roles as $role) {
            $lookup->execute(['slug' => $role]);
            $roleId = $lookup->fetchColumn();
            if ($roleId === false) {
                continue;
            }

            $insert->execute([
                'user_id' => $id,
                'role_id' => (int) $roleId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $primaryRole = $this->primaryRoleFromRoles($roles);
        $update = $connection->prepare(
            'UPDATE users SET role = :role, updated_at = :updated_at WHERE id = :id'
        );
        $update->execute([
            'role' => $primaryRole,
            'updated_at' => date('Y-m-d H:i:s'),
            'id' => $id,
        ]);

        $connection->commit();
    }

    /**
     * @param list<string> $roles
     */
    private function primaryRoleFromRoles(array $roles): string
    {
        foreach (self::PRIMARY_ROLE_PRIORITY as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return $roles[0];
    }
}
