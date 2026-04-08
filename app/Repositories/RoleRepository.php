<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class RoleRepository
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
     * @return list<RoleRow>
     */
    public function allRoles(): array
    {
        $statement = $this->database->query(
            'SELECT roles.*,
                (SELECT COUNT(*) FROM user_roles WHERE user_roles.role_id = roles.id) AS user_count
             FROM roles
             ORDER BY roles.name ASC'
        );

        /** @var list<RoleRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @return list<PermissionRow>
     */
    public function allPermissions(): array
    {
        $statement = $this->database->query(
            'SELECT * FROM permissions ORDER BY module ASC, label ASC'
        );

        /** @var list<PermissionRow> $rows */
        $rows = rows_value($statement->fetchAll(PDO::FETCH_ASSOC));

        return $rows;
    }

    /**
     * @return RoleRow|null
     */
    public function findBySlug(string $slug): ?array
    {
        $statement = $this->database->connection()->prepare('SELECT * FROM roles WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $role = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($role)) {
            return null;
        }

        /** @var RoleRow $role */
        return $role;
    }

    public function slugExists(string $slug, ?string $exceptSlug = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM roles WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($exceptSlug !== null) {
            $sql .= ' AND slug != :except_slug';
            $params['except_slug'] = $exceptSlug;
        }

        $statement = $this->database->connection()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public function createRole(string $slug, string $name, ?string $description): void
    {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO roles (slug, name, description, created_at, updated_at)
             VALUES (:slug, :name, :description, :created_at, :updated_at)'
        );
        $now = date('Y-m-d H:i:s');

        $statement->execute([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateRole(string $slug, string $name, ?string $description): bool
    {
        $statement = $this->database->connection()->prepare(
            'UPDATE roles
             SET name = :name,
                 description = :description,
                 updated_at = :updated_at
             WHERE slug = :slug'
        );
        $statement->execute([
            'name' => $name,
            'description' => $description,
            'updated_at' => date('Y-m-d H:i:s'),
            'slug' => $slug,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * @return list<string>
     */
    public function permissionsForRole(string $roleSlug): array
    {
        return $this->permissionsForRoles([$roleSlug]);
    }

    /**
     * @param list<string> $roleSlugs
     * @return list<string>
     */
    public function permissionsForRoles(array $roleSlugs): array
    {
        $roleSlugs = array_values(array_unique(array_filter(array_map(
            static fn ($slug): string => trim(string_value($slug)),
            $roleSlugs
        ), static fn (string $slug): bool => $slug !== '')));

        if ($roleSlugs === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($roleSlugs), '?'));
        $statement = $this->database->connection()->prepare(
            'SELECT permissions.code
             FROM role_permissions
             INNER JOIN roles ON roles.id = role_permissions.role_id
             INNER JOIN permissions ON permissions.id = role_permissions.permission_id
             WHERE roles.slug IN (' . $placeholders . ')
             ORDER BY permissions.code ASC'
        );
        $statement->execute($roleSlugs);

        return array_values(array_unique(array_filter($statement->fetchAll(PDO::FETCH_COLUMN), static fn ($value): bool => is_string($value))));
    }

    /**
     * @return list<string>
     */
    public function rolesForUserId(int $userId): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT roles.slug
             FROM user_roles
             INNER JOIN roles ON roles.id = user_roles.role_id
             WHERE user_roles.user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        return array_values(array_filter($statement->fetchAll(PDO::FETCH_COLUMN), static fn ($value): bool => is_string($value)));
    }

    /**
     * @param list<string> $roleSlugs
     */
    public function primaryRoleSlug(array $roleSlugs): string
    {
        $roleSlugs = array_values(array_unique(array_filter(array_map(
            static fn ($slug): string => trim(string_value($slug)),
            $roleSlugs
        ), static fn (string $slug): bool => $slug !== '')));

        foreach (self::PRIMARY_ROLE_PRIORITY as $slug) {
            if (in_array($slug, $roleSlugs, true)) {
                return $slug;
            }
        }

        return $roleSlugs[0] ?? 'guest';
    }

    /**
     * @return array{roles: list<RoleRow>, permissions: list<PermissionRow>, matrix: array<string, list<string>>}
     */
    public function permissionMatrix(): array
    {
        $roles = $this->allRoles();
        $permissions = $this->allPermissions();
        $matrix = [];

        foreach ($roles as $role) {
            $slug = map_string($role, 'slug');
            $matrix[$slug] = $this->permissionsForRole($slug);
        }

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'matrix' => $matrix,
        ];
    }

    /**
     * @param list<string> $permissionCodes
     */
    public function syncPermissions(string $roleSlug, array $permissionCodes): void
    {
        $connection = $this->database->connection();
        $connection->beginTransaction();

        $roleId = $this->roleIdBySlug($roleSlug);
        if ($roleId === null) {
            $connection->rollBack();
            return;
        }

        $delete = $connection->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
        $delete->execute(['role_id' => $roleId]);

        if ($permissionCodes !== []) {
            $lookup = $connection->prepare('SELECT id FROM permissions WHERE code = :code LIMIT 1');
            $insert = $connection->prepare(
                'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
            );

            foreach ($permissionCodes as $code) {
                $lookup->execute(['code' => $code]);
                $permissionId = $lookup->fetchColumn();
                if ($permissionId === false) {
                    continue;
                }

                $insert->execute([
                    'role_id' => $roleId,
                    'permission_id' => (int) $permissionId,
                ]);
            }
        }

        $connection->commit();
    }

    private function roleIdBySlug(string $roleSlug): ?int
    {
        $statement = $this->database->connection()->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $roleSlug]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}
