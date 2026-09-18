<?php

namespace App\Application\Admin\Contracts;

use App\Domain\Admin\Entities\Role;
use App\Domain\Admin\ValueObjects\RoleId;
use App\Domain\Shared\Contracts\RepositoryInterface;

interface RoleRepositoryInterface extends RepositoryInterface
{
    public function find(RoleId $roleId): ?Role;

    public function findBySlug(string $slug): ?Role;

    /**
     * @return list<Role>
     */
    public function all(): array;

    /**
     * @param  list<RoleId>  $roleIds
     * @return array<string, list<string>> role id => permissions
     */
    public function permissionsFor(array $roleIds): array;

    public function save(Role $role): void;

    public function softDelete(RoleId $roleId): void;
}
