<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Admin\Contracts\AdminRepositoryInterface;
use App\Domain\Admin\Entities\Admin;
use App\Domain\Admin\ValueObjects\AdminId;
use App\Infrastructure\Persistence\MongoDB\Documents\AdminDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\AdminMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;

final readonly class MongoAdminRepository implements AdminRepositoryInterface
{
    public function __construct(private AdminMapper $mapper) {}

    public function find(AdminId $adminId): ?Admin
    {
        $document = AdminDocument::query()->find((string) $adminId);

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findByUsername(string $username): ?Admin
    {
        $document = AdminDocument::query()->where('username', strtolower(trim($username)))->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function paginate(int $page, int $perPage, ?string $search = null, ?string $status = null): array
    {
        $query = AdminDocument::query();

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($search !== null && trim($search) !== '') {
            $regex = new \MongoDB\BSON\Regex(preg_quote(trim($search)), 'i');
            $query->where(function ($q) use ($regex): void {
                $q->where('username', 'regex', $regex)
                    ->orWhere('name', 'regex', $regex)
                    ->orWhere('email', 'regex', $regex);
            });
        }

        $total = (clone $query)->count();
        $items = $query
            ->orderBy('created_at', 'asc')
            ->skip(max(0, $page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn (AdminDocument $document): Admin => $this->mapper->toDomain($document))
            ->all();

        return ['items' => array_values($items), 'total' => $total];
    }

    public function countSuperAdmins(): int
    {
        return AdminDocument::query()
            ->where('is_super_admin', true)
            ->where('status', Admin::STATUS_ACTIVE)
            ->count();
    }

    public function save(Admin $admin): void
    {
        ExplicitIdPersister::save($this->mapper->toDocument($admin), (string) $admin->id());
    }

    public function softDelete(AdminId $adminId): void
    {
        AdminDocument::query()->find((string) $adminId)?->delete();
    }
}
