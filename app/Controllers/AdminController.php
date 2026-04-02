<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Response;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\BackupStatusService;
use App\Services\HealthService;
use App\Services\OpsAlertService;

final class AdminController
{
    public function __construct(
        private readonly Response $response,
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly HealthService $health,
        private readonly Logger $logger,
        private readonly BackupStatusService $backupStatus,
        private readonly OpsAlertService $opsAlerts
    ) {
    }

    public function users(): void
    {
        $users = $this->users->all();
        $roles = $this->roles->allRoles();
        $effectivePermissions = [];

        foreach ($users as $user) {
            $effectivePermissions[(int) ($user['id'] ?? 0)] = $this->roles->permissionsForRoles((array) ($user['roles'] ?? []));
        }

        $this->response->view('admin/users', [
            'users' => $users,
            'roles' => $roles,
            'effectivePermissions' => $effectivePermissions,
        ]);
    }

    public function updateUserRole(int $id): void
    {
        $roles = strings_value($_POST['roles'] ?? []);
        $availableRoles = array_map(static fn (array $role): string => map_string($role, 'slug'), $this->roles->allRoles());

        if ($roles === []) {
            $this->response->redirect('/admin/users', 'Assign at least one role to the user.', 'error');
        }

        foreach ($roles as $role) {
            if (!in_array($role, $availableRoles, true)) {
                $this->response->redirect('/admin/users', 'One of the selected roles is not valid.', 'error');
            }
        }

        $primaryRole = $this->roles->primaryRoleSlug($roles);
        usort($roles, static fn (string $left, string $right): int => $left === $primaryRole ? -1 : ($right === $primaryRole ? 1 : strcmp($left, $right)));

        $this->users->updateRoles($id, $roles);

        $this->response->redirect('/admin/users', 'User roles updated successfully.');
    }

    public function roles(): void
    {
        $matrix = $this->roles->permissionMatrix();

        $this->response->view('admin/roles', $matrix);
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
}
