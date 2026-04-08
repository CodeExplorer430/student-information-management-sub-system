<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\AccountService;
use App\Services\BackupStatusService;
use App\Services\HealthService;
use App\Services\OpsAlertService;
use RuntimeException;

final class AdminController
{
    public function __construct(
        private readonly Response $response,
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly HealthService $health,
        private readonly Logger $logger,
        private readonly BackupStatusService $backupStatus,
        private readonly OpsAlertService $opsAlerts,
        private readonly AccountService $accounts,
        private readonly Validator $validator
    ) {
    }

    public function users(): void
    {
        $users = $this->users->all();
        $roles = $this->roles->allRoles();
        $effectivePermissions = [];
        $permissionCatalog = [];
        $effectivePermissionSummaries = [];

        foreach ($this->roles->allPermissions() as $permission) {
            $code = map_string($permission, 'code');
            $permissionCatalog[$code] = [
                'code' => $code,
                'label' => map_string($permission, 'label'),
                'module' => map_string($permission, 'module'),
                'description' => map_string($permission, 'description'),
            ];
        }

        foreach ($users as $user) {
            $userId = (int) ($user['id'] ?? 0);
            $effectivePermissions[$userId] = $this->roles->permissionsForRoles((array) ($user['roles'] ?? []));
            $effectivePermissionSummaries[$userId] = $this->summarizePermissions($effectivePermissions[$userId], $permissionCatalog);
        }

        $this->response->view('admin/users', [
            'users' => $users,
            'roles' => $roles,
            'effectivePermissions' => $effectivePermissions,
            'effectivePermissionSummaries' => $effectivePermissionSummaries,
        ]);
    }

    public function updateUserRole(int $id): void
    {
        if ($this->users->find($id) === null) {
            $this->response->view('partials/404', [], 404);
        }

        $role = trim(string_value($_POST['role'] ?? ''));
        $availableRoles = array_map(static fn (array $role): string => map_string($role, 'slug'), $this->roles->allRoles());

        if ($role === '') {
            $this->response->back('/admin/users', 'Select a role for the user.', 'error');
        }

        if (!in_array($role, $availableRoles, true)) {
            $this->response->back('/admin/users', 'Selected role is not valid.', 'error');
        }

        $this->users->updateRoles($id, [$role]);

        $this->response->back('/admin/users', 'User role updated successfully.');
    }

    public function editUser(int $id): void
    {
        $user = $this->users->find($id);
        if ($user === null) {
            $this->response->view('partials/404', [], 404);
        }

        $roles = $this->roles->allRoles();
        $permissionCatalog = [];

        foreach ($this->roles->allPermissions() as $permission) {
            $code = map_string($permission, 'code');
            $permissionCatalog[$code] = [
                'code' => $code,
                'label' => map_string($permission, 'label'),
                'module' => map_string($permission, 'module'),
                'description' => map_string($permission, 'description'),
            ];
        }

        $summary = $this->summarizePermissions($this->roles->permissionsForRoles(strings_value($user['roles'] ?? [])), $permissionCatalog);

        $this->response->view('admin/user-edit', [
            'userAccount' => $user,
            'roles' => $roles,
            'permissionSummary' => $summary,
        ]);
    }

    public function updateUserAccount(int $id): void
    {
        $user = $this->users->find($id);
        if ($user === null) {
            $this->response->view('partials/404', [], 404);
        }

        [$errors, $data] = $this->validator->validate(map_value($_POST), $this->accountRules());
        $files = uploaded_file_value($_FILES['photo'] ?? null);

        if ($errors !== []) {
            $this->renderUserEdit($this->mergeUserFormData($user), $errors, 422);
        }

        $payload = [
            'name' => string_value($data['name'] ?? ''),
            'email' => string_value($data['email'] ?? ''),
            'mobile_phone' => nullable_string_value($_POST['mobile_phone'] ?? null),
            'department' => nullable_string_value($_POST['department'] ?? null),
        ];

        try {
            $this->accounts->update($id, $payload, $files !== [] ? ['photo' => $files] : []);
        } catch (RuntimeException $exception) {
            $this->renderUserEdit($this->mergeUserFormData($user), $this->accountErrorsForException($exception), 422);
        }

        $this->response->redirect('/admin/users/' . $id . '/edit', 'User account details updated successfully.');
    }

    public function resetUserPassword(int $id): void
    {
        $user = $this->users->find($id);
        if ($user === null) {
            $this->response->view('partials/404', [], 404);
        }

        $password = trim(string_value($_POST['password'] ?? ''));
        $confirmation = trim(string_value($_POST['password_confirmation'] ?? ''));

        if ($password === '') {
            $this->renderUserEdit($user, ['password' => ['Provide a password for the reset.']], 422);
        }

        if ($password !== $confirmation) {
            $this->renderUserEdit($user, ['password_confirmation' => ['Password confirmation does not match.']], 422);
        }

        $this->accounts->resetPassword($id, $password);

        $this->response->redirect('/admin/users/' . $id . '/edit', 'Password reset successfully.');
    }

    public function roles(): void
    {
        $matrix = $this->roles->permissionMatrix();

        $this->response->view('admin/roles', $matrix);
    }

    public function createRole(): void
    {
        [$errors, $data] = $this->roleFormData();

        if ($errors !== []) {
            $this->response->back('/admin/roles', implode(' ', $errors), 'error');
        }

        if ($this->roles->slugExists($data['slug'])) {
            $this->response->back('/admin/roles', 'Role slug already exists.', 'error');
        }

        $this->roles->createRole($data['slug'], $data['name'], $data['description']);

        $this->response->redirect('/admin/roles', 'Role created successfully.');
    }

    public function updateRole(string $slug): void
    {
        $role = $this->roles->findBySlug($slug);
        if ($role === null) {
            $this->response->redirect('/admin/roles', 'Role was not found.', 'error');
        }

        [$errors, $data] = $this->roleFormData(false);

        if ($errors !== []) {
            $this->response->back('/admin/roles', implode(' ', $errors), 'error');
        }

        $this->roles->updateRole($slug, $data['name'], $data['description']);

        $this->response->redirect('/admin/roles', 'Role updated successfully.');
    }

    public function syncRolePermissions(string $slug): void
    {
        $permissionCodes = array_values(array_filter((array) ($_POST['permissions'] ?? []), static fn ($value): bool => is_string($value) && $value !== ''));
        $this->roles->syncPermissions($slug, $permissionCodes);

        $this->response->redirect('/admin/roles', 'Role permissions updated successfully.');
    }

    public function diagnostics(): void
    {
        $this->response->view('admin/diagnostics', [
            'health' => $this->health->ready(),
            'deploymentReadiness' => $this->health->deploymentReadiness(),
            'directoryStatus' => $this->health->directories(),
            'assetStatus' => $this->health->assets(),
            'backupStatus' => $this->backupStatus->report(),
            'opsAlerts' => $this->opsAlerts->report(),
            'recentLogs' => $this->logger->recent(12),
        ]);
    }

    /**
     * @param list<string> $codes
     * @param array<string, array{code: string, label: string, module: string, description: string}> $catalog
     * @return array{
     *     details: list<array{code: string, label: string, module: string, description: string}>,
     *     module_count: int,
     *     modules: list<string>
     * }
     */
    private function summarizePermissions(array $codes, array $catalog): array
    {
        $details = [];
        $modules = [];

        foreach ($codes as $code) {
            $detail = $catalog[$code] ?? ['code' => $code, 'label' => ucwords(str_replace(['.', '_'], ' ', $code)), 'module' => 'general', 'description' => ''];
            $details[] = $detail;
            $modules[$detail['module']] = true;
        }

        return [
            'details' => $details,
            'module_count' => count($modules),
            'modules' => array_keys($modules),
        ];
    }

    /**
     * @return ValidationRules
     */
    private function accountRules(): array
    {
        return [
            'name' => 'required',
            'email' => ['required', 'email'],
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param ValidationErrors $errors
     */
    private function renderUserEdit(array $user, array $errors, int $status = 422): never
    {
        $_SESSION['_old'] = $_POST;
        $roles = $this->roles->allRoles();
        $permissionCatalog = [];

        foreach ($this->roles->allPermissions() as $permission) {
            $code = map_string($permission, 'code');
            $permissionCatalog[$code] = [
                'code' => $code,
                'label' => map_string($permission, 'label'),
                'module' => map_string($permission, 'module'),
                'description' => map_string($permission, 'description'),
            ];
        }

        $this->response->view('admin/user-edit', [
            'userAccount' => $user,
            'errors' => $errors,
            'roles' => $roles,
            'permissionSummary' => $this->summarizePermissions(
                $this->roles->permissionsForRoles(strings_value($user['roles'] ?? [])),
                $permissionCatalog
            ),
        ], $status);
    }

    /**
     * @return ValidationErrors
     */
    private function accountErrorsForException(RuntimeException $exception): array
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'email address')) {
            return ['email' => [$message]];
        }

        if (str_contains($message, 'Photo') || str_contains($message, 'JPG') || str_contains($message, 'PNG') || str_contains($message, 'WEBP')) {
            return ['photo' => [$message]];
        }

        return ['name' => [$message]];
    }

    /**
     * @param UserRow $user
     * @return array<string, mixed>
     */
    private function mergeUserFormData(array $user): array
    {
        $merged = $user;

        foreach (map_value($_POST) as $key => $value) {
            if (is_string($key)) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @return array{0: list<string>, 1: array{slug: string, name: string, description: string|null}}
     */
    private function roleFormData(bool $validateSlug = true): array
    {
        $name = trim(string_value($_POST['name'] ?? ''));
        $slug = trim(string_value($_POST['slug'] ?? ''));
        $description = nullable_string_value($_POST['description'] ?? null);
        $description = $description === null ? null : trim($description);
        $errors = [];

        if ($name === '') {
            $errors[] = 'Role name is required.';
        }

        if (!$validateSlug) {
            $slug = '';
        } elseif ($slug === '') {
            $errors[] = 'Role slug is required.';
        } elseif (preg_match('/^[a-z0-9][a-z0-9_-]{0,49}$/', $slug) !== 1) {
            $errors[] = 'Role slug must use up to 50 lowercase letters, numbers, dashes, or underscores.';
        }

        return [$errors, [
            'slug' => $slug,
            'name' => $name,
            'description' => $description === '' ? null : $description,
        ]];
    }
}
