<?php

namespace App\Application\Admin\Services;

use App\Application\Admin\Contracts\RoleRepositoryInterface;
use App\Domain\Admin\Entities\Admin;

/**
 * Resolves an admin's effective permission list (roles ∪ direct; super admin ⇒ everything).
 */
final readonly class AdminAccessResolver
{
    public function __construct(private RoleRepositoryInterface $roles) {}

    /**
     * @return list<string>
     */
    public function permissionsFor(Admin $admin): array
    {
        if ($admin->isSuperAdmin()) {
            return $admin->effectivePermissions([]);
        }

        return $admin->effectivePermissions($this->roles->permissionsFor($admin->roleIds()));
    }

    public function can(Admin $admin, string $permission): bool
    {
        return $admin->isSuperAdmin() || in_array($permission, $this->permissionsFor($admin), true);
    }
}
