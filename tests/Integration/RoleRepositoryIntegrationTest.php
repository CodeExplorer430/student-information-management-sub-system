<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Tests\Support\IntegrationTestCase;

final class RoleRepositoryIntegrationTest extends IntegrationTestCase
{
    public function testRoleRepositoryExposesAndUpdatesPermissionMatrix(): void
    {
        $repository = $this->app->get(RoleRepository::class);

        $studentPermissions = $repository->permissionsForRole('student');
        self::assertContains('requests.create', $studentPermissions);
        self::assertContains('dashboard.view_student', $studentPermissions);
        self::assertContains('students.view_own', $studentPermissions);
        self::assertNotContains('students.view', $studentPermissions);

        $repository->syncPermissions('faculty', ['records.view', 'dashboard.view_operations', 'requests.view_queue']);

        $facultyPermissions = $repository->permissionsForRole('faculty');
        self::assertContains('requests.view_queue', $facultyPermissions);

        $combinedPermissions = $repository->permissionsForRoles(['student', 'faculty']);
        $admin = $this->app->get(UserRepository::class)->findByEmail('admin@bcp.edu');

        self::assertNotNull($admin);
        self::assertContains('requests.create', $combinedPermissions);
        self::assertContains('records.view', $combinedPermissions);
        self::assertContains('admin', $repository->rolesForUserId((int) $admin['id']));
        self::assertSame('registrar', $repository->primaryRoleSlug(['student', 'registrar']));
        self::assertSame('guest', $repository->primaryRoleSlug([]));
        self::assertNotEmpty($repository->allRoles());
        self::assertNotEmpty($repository->allPermissions());
        self::assertArrayHasKey('roles', $repository->permissionMatrix());
        self::assertSame([], $repository->permissionsForRoles(['', ' ', '']));

        $repository->createRole('support_ops', 'Support Operations', 'Handles escalated support queues.');
        self::assertTrue($repository->slugExists('support_ops'));
        self::assertNotNull($repository->findBySlug('support_ops'));

        $repository->updateRole('support_ops', 'Support Services', 'Handles student services queues.');
        $updatedRole = $repository->findBySlug('support_ops');
        self::assertNotNull($updatedRole);
        self::assertSame('Support Services', $updatedRole['name']);
        self::assertFalse($repository->slugExists('support_ops', 'support_ops'));
        self::assertTrue($repository->slugExists('support_ops', 'student'));
        self::assertFalse($repository->updateRole('missing-role', 'Missing Role', null));

        $repository->syncPermissions('missing-role', ['students.view']);
        self::assertContains('records.view', $repository->permissionsForRole('faculty'));
    }
}
