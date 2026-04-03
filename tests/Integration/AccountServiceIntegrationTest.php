<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\UserRepository;
use App\Services\AccountService;
use RuntimeException;
use Tests\Support\IntegrationTestCase;

final class AccountServiceIntegrationTest extends IntegrationTestCase
{
    public function testUpdateRejectsMissingUser(): void
    {
        $service = $this->app->get(AccountService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User account not found.');
        $service->update(9999, [
            'name' => 'Missing User',
            'email' => 'missing@bcp.edu',
        ], []);
    }

    public function testUpdatePersistsAccountDetailsAndUploadedAvatar(): void
    {
        $service = $this->app->get(AccountService::class);
        $users = $this->app->get(UserRepository::class);
        $user = $users->findByEmail('staff@bcp.edu');

        self::assertNotNull($user);

        $imageFile = tempnam(sys_get_temp_dir(), 'sims-user-avatar-');
        self::assertNotFalse($imageFile);
        file_put_contents(
            $imageFile,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0GQAAAAASUVORK5CYII=', true)
        );

        $GLOBALS['__sims_test_move_uploaded_files'] = true;

        try {
            $service->update((int) $user['id'], [
                'name' => 'Staff User Updated',
                'email' => 'staff@bcp.edu',
                'mobile_phone' => '09173334444',
                'department' => 'Operations',
            ], [
                'photo' => [
                    'name' => 'avatar.png',
                    'tmp_name' => $imageFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($imageFile),
                ],
            ]);
        } finally {
            unset($GLOBALS['__sims_test_move_uploaded_files']);
            @unlink($imageFile);
        }

        $updated = $users->find((int) $user['id']);

        self::assertNotNull($updated);
        self::assertSame('Staff User Updated', $updated['name']);
        self::assertSame('09173334444', $updated['mobile_phone']);
        self::assertSame('Operations', $updated['department']);
        $photoPath = nullable_string_value($updated['photo_path'] ?? null);

        self::assertNotNull($photoPath);
        self::assertStringStartsWith('user-avatar-', $photoPath);
        self::assertFileExists(dirname(__DIR__, 2) . '/storage/app/private/uploads/' . $photoPath);
    }

    public function testUpdateRejectsDuplicateEmail(): void
    {
        $service = $this->app->get(AccountService::class);
        $user = $this->app->get(UserRepository::class)->findByEmail('staff@bcp.edu');

        self::assertNotNull($user);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already assigned to another user');
        $service->update((int) $user['id'], [
            'name' => 'Staff User',
            'email' => 'admin@bcp.edu',
            'mobile_phone' => '',
            'department' => 'Student Affairs',
        ], []);
    }

    public function testUpdateNormalizesMissingOptionalFieldsToNull(): void
    {
        $service = $this->app->get(AccountService::class);
        $users = $this->app->get(UserRepository::class);
        $user = $users->findByEmail('staff@bcp.edu');

        self::assertNotNull($user);

        $service->update((int) $user['id'], [
            'name' => 'Staff User Nullable',
            'email' => 'staff@bcp.edu',
            'department' => '',
        ], []);

        $updated = $users->find((int) $user['id']);

        self::assertNotNull($updated);
        self::assertNull($updated['mobile_phone']);
        self::assertSame('', $updated['department']);
    }

    public function testResetPasswordRejectsInvalidStatesAndUpdatesHash(): void
    {
        $service = $this->app->get(AccountService::class);
        $users = $this->app->get(UserRepository::class);
        $user = $users->findByEmail('staff@bcp.edu');

        self::assertNotNull($user);

        try {
            $service->resetPassword(9999, 'Missing123!');
            self::fail('Expected missing user failure.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('User account not found.', $exception->getMessage());
        }

        try {
            $service->resetPassword((int) $user['id'], '   ');
            self::fail('Expected blank password failure.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Provide a password for the reset.', $exception->getMessage());
        }

        $service->resetPassword((int) $user['id'], 'ServiceReset123!');

        $updated = $users->find((int) $user['id']);

        self::assertNotNull($updated);
        self::assertTrue(password_verify('ServiceReset123!', (string) $updated['password_hash']));
    }
}
