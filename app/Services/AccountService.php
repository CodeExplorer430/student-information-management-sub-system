<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use RuntimeException;

final class AccountService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly FileStorageService $files,
        private readonly AuditService $auditService
    ) {
    }

    /**
     * @param array{name: string, email: string, mobile_phone?: string|null, department?: string|null} $data
     * @param array{photo?: array{name?: string, tmp_name?: string, error?: int, size?: int|false}} $files
     */
    public function update(int $id, array $data, array $files): void
    {
        $user = $this->users->find($id);
        if ($user === null) {
            throw new RuntimeException('User account not found.');
        }

        $email = trim($data['email']);
        if ($this->users->emailExists($email, $id)) {
            throw new RuntimeException('This email address is already assigned to another user.');
        }

        $photoPath = nullable_string_value($user['photo_path'] ?? null);
        $uploaded = $this->files->storeImage($files['photo'] ?? [], 'user-avatar');
        if ($uploaded !== null) {
            $photoPath = $uploaded;
        }

        $payload = [
            'name' => trim($data['name']),
            'email' => $email,
            'mobile_phone' => $this->optionalString($data, 'mobile_phone'),
            'department' => trim(string_value($data['department'] ?? '')),
            'photo_path' => $photoPath,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->users->updateAccount($id, $payload);
        $this->auditService->log('user', $id, 'updated', $this->auditPayload($user), $payload);
    }

    public function resetPassword(int $id, string $password): void
    {
        $user = $this->users->find($id);
        if ($user === null) {
            throw new RuntimeException('User account not found.');
        }

        $normalized = trim($password);
        if ($normalized === '') {
            throw new RuntimeException('Provide a password for the reset.');
        }

        $this->users->updatePassword($id, password_hash($normalized, PASSWORD_DEFAULT));
        $this->auditService->log('user', $id, 'password_reset', null, ['password_reset' => true]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalString(array $data, string $field): ?string
    {
        $value = nullable_string_value($data[$field] ?? null);
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param UserRow $user
     * @return array{name: string, email: string, mobile_phone: string|null, department: string, photo_path: string|null}
     */
    private function auditPayload(array $user): array
    {
        return [
            'name' => map_string($user, 'name'),
            'email' => map_string($user, 'email'),
            'mobile_phone' => nullable_string_value($user['mobile_phone'] ?? null),
            'department' => map_string($user, 'department'),
            'photo_path' => nullable_string_value($user['photo_path'] ?? null),
        ];
    }
}
