<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Repositories\UserRepository;
use ReflectionMethod;
use Tests\Support\IntegrationTestCase;

final class UserRepositoryIntegrationTest extends IntegrationTestCase
{
    public function testUsersExposeAssignedRolesAndDerivedPrimaryRole(): void
    {
        $repository = $this->app->get(UserRepository::class);

        $admin = $repository->findByEmail('admin@bcp.edu');

        self::assertNotNull($admin);
        self::assertContains('admin', $admin['roles']);
        self::assertContains('registrar', $admin['roles']);
        self::assertSame('admin', $admin['role']);

        $repository->updateRoles((int) $admin['id'], ['student', 'faculty']);

        $updated = $repository->find((int) $admin['id']);

        self::assertNotNull($updated);
        self::assertContains('student', $updated['roles']);
        self::assertContains('faculty', $updated['roles']);
        self::assertSame('faculty', $updated['role']);

        $createdId = $repository->create([
            'name' => 'Coverage User',
            'email' => 'coverage.user@bcp.edu',
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
            'roles' => ['student'],
            'department' => 'BSIT',
            'created_at' => '2026-03-31 10:00:00',
            'updated_at' => '2026-03-31 10:00:00',
        ]);
        $repository->updateRole($createdId, 'staff');

        $created = $repository->find($createdId);

        self::assertNotNull($created);
        self::assertContains('staff', $created['roles']);
        self::assertSame('staff', $created['role']);
        self::assertNull($created['mobile_phone']);
        self::assertNull(nullable_string_value($created['photo_path'] ?? null));

        self::assertSame(6, $repository->count());
        self::assertNull($repository->find(9999));
        self::assertCount(6, $repository->all());
        self::assertSame([], $repository->findManyByIds([]));

        $selected = $repository->findManyByIds([$createdId, (int) $admin['id'], 0, -1, $createdId]);

        self::assertCount(2, $selected);
        self::assertContains('Coverage User', array_column($selected, 'name'));
        self::assertContains((string) $admin['name'], array_column($selected, 'name'));

        $repository->updateAccount($createdId, [
            'name' => 'Coverage User Updated',
            'email' => 'coverage.user.updated@bcp.edu',
            'mobile_phone' => '09171234567',
            'department' => 'ICT',
            'photo_path' => 'user-avatar-test.png',
            'updated_at' => '2026-04-03 10:00:00',
        ]);

        $updatedAccount = $repository->find($createdId);

        self::assertNotNull($updatedAccount);
        self::assertSame('Coverage User Updated', $updatedAccount['name']);
        self::assertSame('coverage.user.updated@bcp.edu', $updatedAccount['email']);
        self::assertSame('09171234567', $updatedAccount['mobile_phone']);
        self::assertSame('ICT', $updatedAccount['department']);
        self::assertSame('user-avatar-test.png', nullable_string_value($updatedAccount['photo_path'] ?? null));

        self::assertTrue($repository->emailExists('coverage.user.updated@bcp.edu'));
        self::assertFalse($repository->emailExists('coverage.user.updated@bcp.edu', $createdId));

        $repository->updatePassword($createdId, password_hash('UpdatedPassword123!', PASSWORD_DEFAULT));
        $updatedPassword = $repository->find($createdId);

        self::assertNotNull($updatedPassword);
        self::assertTrue(password_verify('UpdatedPassword123!', (string) $updatedPassword['password_hash']));
    }

    public function testPrivateRoleHelpersCoverNormalizationAndPriorityFallbacks(): void
    {
        $repository = $this->app->get(UserRepository::class);
        $normalizeRoles = new ReflectionMethod(UserRepository::class, 'normalizeRoles');
        $normalizeRoles->setAccessible(true);
        $primaryRole = new ReflectionMethod(UserRepository::class, 'primaryRoleFromRoles');
        $primaryRole->setAccessible(true);

        self::assertSame(['student', 'faculty'], $normalizeRoles->invoke($repository, [' student ', '', 'faculty', 'student']));
        self::assertSame(['registrar'], $normalizeRoles->invoke($repository, ' registrar '));
        self::assertSame('admin', $primaryRole->invoke($repository, ['faculty', 'admin', 'student']));
        self::assertSame('guest', $primaryRole->invoke($repository, ['guest']));

        $rolesForUserIds = new ReflectionMethod(UserRepository::class, 'rolesForUserIds');
        $rolesForUserIds->setAccessible(true);

        $roles = $rolesForUserIds->invoke($repository, [0, -1, 1, 1, 2]);
        self::assertIsArray($roles);

        self::assertArrayHasKey(1, $roles);
        self::assertArrayHasKey(2, $roles);
        self::assertSame([], $rolesForUserIds->invoke($repository, [0, -1]));

        self::assertNull($repository->findByEmail('missing.user@bcp.edu'));

        $createdId = $repository->create([
            'name' => 'Coverage Empty Roles',
            'email' => 'coverage.empty.roles@bcp.edu',
            'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
            'roles' => ['student'],
            'department' => 'BSIT',
            'created_at' => '2026-03-31 10:00:00',
            'updated_at' => '2026-03-31 10:00:00',
        ]);

        $repository->updateRoles($createdId, []);
        $emptyRoleUser = $repository->find($createdId);
        self::assertNotNull($emptyRoleUser);
        self::assertContains('student', $emptyRoleUser['roles']);

        $connection = $this->app->get(\App\Core\Database::class)->connection();
        $connection->exec("INSERT INTO roles (slug, name, created_at, updated_at) VALUES ('', 'Blank Role', '2026-03-31 10:00:00', '2026-03-31 10:00:00')");
        $blankRoleId = (int) $connection->lastInsertId();
        $connection->exec(sprintf(
            "INSERT INTO user_roles (user_id, role_id, created_at) VALUES (%d, %d, '2026-03-31 10:00:00')",
            $createdId,
            $blankRoleId
        ));

        $normalizedRoles = $rolesForUserIds->invoke($repository, [$createdId]);
        self::assertIsArray($normalizedRoles);
        self::assertSame(['student'], $normalizedRoles[$createdId]);

        $repository->updateRoles($createdId, ['missing-role-slug']);
        $afterMissingRole = $repository->find($createdId);
        self::assertNotNull($afterMissingRole);
        self::assertSame('missing-role-slug', $afterMissingRole['role']);
        self::assertSame(['missing-role-slug'], $afterMissingRole['roles']);
    }

    public function testAllReturnsEmptyArrayWhenNoUsersExist(): void
    {
        $databaseFile = tempnam(sys_get_temp_dir(), 'sims-empty-users-');
        self::assertNotFalse($databaseFile);
        $logFile = tempnam(sys_get_temp_dir(), 'sims-empty-users-log-');
        self::assertNotFalse($logFile);

        try {
            $database = new Database(
                new Config([
                    'db' => [
                        'driver' => 'sqlite',
                        'database' => $databaseFile,
                    ],
                ]),
                new Logger($logFile)
            );
            $connection = $database->connection();
            $connection->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, mobile_phone TEXT, photo_path TEXT, role TEXT, department TEXT)');

            $repository = new UserRepository($database);

            self::assertSame([], $repository->all());
        } finally {
            @unlink($databaseFile);
            @unlink($logFile);
        }
    }
}
