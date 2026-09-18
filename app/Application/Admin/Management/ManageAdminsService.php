<?php

namespace App\Application\Admin\Management;

use App\Application\Admin\Contracts\AdminRepositoryInterface;
use App\Application\Admin\Contracts\AdminTokenIssuerInterface;
use App\Application\Admin\Contracts\PasswordHasherInterface;
use App\Application\Admin\Contracts\RoleRepositoryInterface;
use App\Application\Admin\Exceptions\AdminManagementException;
use App\Application\Admin\Services\AuditLogger;
use App\Domain\Admin\Entities\Admin;
use App\Domain\Admin\ValueObjects\AdminId;
use App\Domain\Admin\ValueObjects\RoleId;

final readonly class ManageAdminsService
{
    public function __construct(
        private AdminRepositoryInterface $admins,
        private RoleRepositoryInterface $roles,
        private PasswordHasherInterface $hasher,
        private AdminTokenIssuerInterface $tokens,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{items: list<Admin>, total: int}
     */
    public function list(int $page, int $perPage, ?string $search, ?string $status): array
    {
        return $this->admins->paginate($page, $perPage, $search, $status);
    }

    public function get(string $adminId): Admin
    {
        return $this->admins->find(new AdminId($adminId)) ?? throw AdminManagementException::notFound();
    }

    /**
     * @param  array{username: string, name: string, email?: ?string, password: string, is_super_admin?: bool, role_ids?: list<string>, direct_permissions?: list<string>, locale?: string, must_change_password?: bool}  $input
     */
    public function create(Admin $actor, array $input): Admin
    {
        $username = Admin::normalizeUsername($input['username']);
        if ($this->admins->findByUsername($username) !== null) {
            throw AdminManagementException::usernameTaken($username);
        }

        $roleIds = $this->resolveRoleIds($input['role_ids'] ?? []);

        $admin = Admin::create(
            adminId: new AdminId(bin2hex(random_bytes(16))),
            username: $username,
            name: (string) $input['name'],
            email: $input['email'] ?? null,
            passwordHash: $this->hasher->hash($input['password']),
            isSuperAdmin: (bool) ($input['is_super_admin'] ?? false),
            roleIds: $roleIds,
            directPermissions: (array) ($input['direct_permissions'] ?? []),
            locale: (string) ($input['locale'] ?? 'en'),
            mustChangePassword: (bool) ($input['must_change_password'] ?? true),
        );

        $this->admins->save($admin);
        $this->audit->record($actor, 'admins.created', 'admin', (string) $admin->id(), [
            'username' => $admin->username(),
            'name' => $admin->name(),
            'is_super_admin' => $admin->isSuperAdmin(),
            'role_ids' => array_map('strval', $admin->roleIds()),
            'direct_permissions' => $admin->directPermissions(),
        ]);

        return $admin;
    }

    /**
     * @param  array{name?: string, email?: ?string, locale?: string, is_super_admin?: bool, role_ids?: list<string>, direct_permissions?: list<string>, status?: string}  $input
     */
    public function update(Admin $actor, string $adminId, array $input): Admin
    {
        $admin = $this->get($adminId);
        $before = $this->snapshot($admin);
        $isSelf = (string) $actor->id() === (string) $admin->id();

        if (array_key_exists('name', $input) || array_key_exists('email', $input) || array_key_exists('locale', $input)) {
            $admin->rename(
                (string) ($input['name'] ?? $admin->name()),
                array_key_exists('email', $input) ? $input['email'] : $admin->email(),
                (string) ($input['locale'] ?? $admin->locale()),
            );
        }

        $touchesAccess = array_key_exists('is_super_admin', $input)
            || array_key_exists('role_ids', $input)
            || array_key_exists('direct_permissions', $input)
            || array_key_exists('status', $input);

        if ($touchesAccess) {
            if ($isSelf) {
                throw AdminManagementException::cannotModifySelfAccess();
            }

            $newSuper = (bool) ($input['is_super_admin'] ?? $admin->isSuperAdmin());
            $newStatus = (string) ($input['status'] ?? $admin->status());

            if ($admin->isSuperAdmin() && ($newSuper === false || $newStatus !== Admin::STATUS_ACTIVE) && $this->admins->countSuperAdmins() <= 1) {
                throw AdminManagementException::lastSuperAdmin();
            }

            $admin->assignAccess(
                array_key_exists('role_ids', $input) ? $this->resolveRoleIds((array) $input['role_ids']) : $admin->roleIds(),
                array_key_exists('direct_permissions', $input) ? (array) $input['direct_permissions'] : $admin->directPermissions(),
                $newSuper,
            );

            if ($newStatus === Admin::STATUS_SUSPENDED && $admin->isActive()) {
                $admin->suspend();
                $this->tokens->revokeAll($admin->id());
            } elseif ($newStatus === Admin::STATUS_ACTIVE && ! $admin->isActive()) {
                $admin->activate();
            }
        }

        $this->admins->save($admin);
        $this->audit->record($actor, 'admins.updated', 'admin', (string) $admin->id(), [
            'before' => $before,
            'after' => $this->snapshot($admin),
        ]);

        return $admin;
    }

    public function resetPassword(Admin $actor, string $adminId, string $newPassword, bool $mustChange = true): Admin
    {
        $admin = $this->get($adminId);
        $admin->changePassword($this->hasher->hash($newPassword), $mustChange);
        $this->admins->save($admin);
        $this->tokens->revokeAll($admin->id());
        $this->audit->record($actor, 'admins.password_reset', 'admin', (string) $admin->id(), [], ['must_change' => $mustChange]);

        return $admin;
    }

    public function delete(Admin $actor, string $adminId): void
    {
        $admin = $this->get($adminId);
        if ((string) $actor->id() === (string) $admin->id()) {
            throw AdminManagementException::cannotModifySelfAccess();
        }
        if ($admin->isSuperAdmin() && $this->admins->countSuperAdmins() <= 1) {
            throw AdminManagementException::lastSuperAdmin();
        }

        $this->tokens->revokeAll($admin->id());
        $this->admins->softDelete($admin->id());
        $this->audit->record($actor, 'admins.deleted', 'admin', (string) $admin->id(), ['username' => $admin->username()]);
    }

    /**
     * @param  list<string>  $ids
     * @return list<RoleId>
     */
    private function resolveRoleIds(array $ids): array
    {
        $known = [];
        foreach ($this->roles->all() as $role) {
            $known[(string) $role->id()] = true;
        }

        $resolved = [];
        foreach (array_unique(array_map('strval', $ids)) as $id) {
            if (! isset($known[$id])) {
                throw AdminManagementException::unknownRole($id);
            }
            $resolved[] = new RoleId($id);
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Admin $admin): array
    {
        return [
            'name' => $admin->name(),
            'email' => $admin->email(),
            'locale' => $admin->locale(),
            'status' => $admin->status(),
            'is_super_admin' => $admin->isSuperAdmin(),
            'role_ids' => array_map('strval', $admin->roleIds()),
            'direct_permissions' => $admin->directPermissions(),
        ];
    }
}
