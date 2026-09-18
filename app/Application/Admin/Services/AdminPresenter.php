<?php

namespace App\Application\Admin\Services;

use App\Application\Admin\Contracts\RoleRepositoryInterface;
use App\Domain\Admin\Entities\Admin;
use App\Domain\Admin\Entities\Role;
use DateTimeInterface;

/**
 * Shapes domain entities into API payloads (single place so Next.js types stay stable).
 */
final readonly class AdminPresenter
{
    public function __construct(
        private RoleRepositoryInterface $roles,
        private AdminAccessResolver $access,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function admin(Admin $admin, bool $withPermissions = true): array
    {
        $roleIds = array_map(static fn ($id): string => (string) $id, $admin->roleIds());
        $roles = [];
        foreach ($this->roles->all() as $role) {
            if (in_array((string) $role->id(), $roleIds, true)) {
                $roles[] = ['id' => (string) $role->id(), 'slug' => $role->slug, 'name' => $role->name()];
            }
        }

        $payload = [
            'id' => (string) $admin->id(),
            'username' => $admin->username(),
            'name' => $admin->name(),
            'email' => $admin->email(),
            'is_super_admin' => $admin->isSuperAdmin(),
            'status' => $admin->status(),
            'locale' => $admin->locale(),
            'roles' => $roles,
            'role_ids' => $roleIds,
            'direct_permissions' => $admin->directPermissions(),
            'must_change_password' => $admin->mustChangePassword(),
            'last_login_at' => $admin->lastLoginAt()?->format(DateTimeInterface::ATOM),
            'created_at' => $admin->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $admin->updatedAt()->format(DateTimeInterface::ATOM),
        ];

        if ($withPermissions) {
            $payload['permissions'] = $this->access->permissionsFor($admin);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function role(Role $role): array
    {
        return [
            'id' => (string) $role->id(),
            'slug' => $role->slug,
            'name' => $role->name(),
            'description' => $role->description(),
            'permissions' => $role->permissions(),
            'is_system' => $role->isSystem,
            'created_at' => $role->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $role->updatedAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
