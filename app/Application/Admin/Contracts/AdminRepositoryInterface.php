<?php

namespace App\Application\Admin\Contracts;

use App\Domain\Admin\Entities\Admin;
use App\Domain\Admin\ValueObjects\AdminId;
use App\Domain\Shared\Contracts\RepositoryInterface;

interface AdminRepositoryInterface extends RepositoryInterface
{
    public function find(AdminId $adminId): ?Admin;

    public function findByUsername(string $username): ?Admin;

    /**
     * @return array{items: list<Admin>, total: int}
     */
    public function paginate(int $page, int $perPage, ?string $search = null, ?string $status = null): array;

    public function countSuperAdmins(): int;

    public function save(Admin $admin): void;

    public function softDelete(AdminId $adminId): void;
}
