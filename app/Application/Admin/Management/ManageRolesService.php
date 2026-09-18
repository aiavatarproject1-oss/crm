<?php

namespace App\Application\Admin\Management;

use App\Application\Admin\Contracts\RoleRepositoryInterface;
use App\Application\Admin\Exceptions\AdminManagementException;
use App\Application\Admin\Services\AuditLogger;
use App\Domain\Admin\Entities\Admin;
use App\Domain\Admin\Entities\Role;
use App\Domain\Admin\ValueObjects\RoleId;

final readonly class ManageRolesService
{
    public function __construct(
        private RoleRepositoryInterface $roles,
        private AuditLogger $audit,
    ) {}

    /**
     * @return list<Role>
     */
    public function all(): array
    {
        return $this->roles->all();
    }

    public function get(string $roleId): Role
    {
        return $this->roles->find(new RoleId($roleId)) ?? throw AdminManagementException::notFound('Role');
    }

    /**
     * @param  array{slug: string, name: string, description?: string, permissions?: list<string>}  $input
     */
    public function create(Admin $actor, array $input): Role
    {
        $slug = Role::normalizeSlug($input['slug']);
        if ($this->roles->findBySlug($slug) !== null) {
            throw AdminManagementException::slugTaken($slug);
        }

        $role = Role::create(
            new RoleId(bin2hex(random_bytes(16))),
            $slug,
            (string) $input['name'],
            (string) ($input['description'] ?? ''),
            (array) ($input['permissions'] ?? []),
        );

        $this->roles->save($role);
        $this->audit->record($actor, 'roles.created', 'role', (string) $role->id(), [
            'slug' => $role->slug,
            'name' => $role->name(),
            'permissions' => $role->permissions(),
        ]);

        return $role;
    }

    /**
     * @param  array{name?: string, description?: string, permissions?: list<string>}  $input
     */
    public function update(Admin $actor, string $roleId, array $input): Role
    {
        $role = $this->get($roleId);
        $before = ['name' => $role->name(), 'description' => $role->description(), 'permissions' => $role->permissions()];

        $role->update(
            (string) ($input['name'] ?? $role->name()),
            (string) ($input['description'] ?? $role->description()),
            array_key_exists('permissions', $input) ? (array) $input['permissions'] : $role->permissions(),
        );

        $this->roles->save($role);
        $this->audit->record($actor, 'roles.updated', 'role', (string) $role->id(), [
            'before' => $before,
            'after' => ['name' => $role->name(), 'description' => $role->description(), 'permissions' => $role->permissions()],
        ]);

        return $role;
    }

    public function delete(Admin $actor, string $roleId): void
    {
        $role = $this->get($roleId);
        if ($role->isSystem) {
            throw AdminManagementException::systemRole();
        }

        $this->roles->softDelete($role->id());
        $this->audit->record($actor, 'roles.deleted', 'role', (string) $role->id(), ['slug' => $role->slug]);
    }
}
