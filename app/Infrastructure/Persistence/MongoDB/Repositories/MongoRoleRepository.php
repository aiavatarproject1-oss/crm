<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Admin\Contracts\RoleRepositoryInterface;
use App\Domain\Admin\Entities\Role;
use App\Domain\Admin\ValueObjects\RoleId;
use App\Infrastructure\Persistence\MongoDB\Documents\RoleDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\RoleMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;

final readonly class MongoRoleRepository implements RoleRepositoryInterface
{
    public function __construct(private RoleMapper $mapper) {}

    public function find(RoleId $roleId): ?Role
    {
        $document = RoleDocument::query()->find((string) $roleId);

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findBySlug(string $slug): ?Role
    {
        $document = RoleDocument::query()->where('slug', $slug)->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function all(): array
    {
        return RoleDocument::query()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (RoleDocument $document): Role => $this->mapper->toDomain($document))
            ->values()
            ->all();
    }

    public function permissionsFor(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $ids = array_map('strval', $roleIds);
        $map = [];
        foreach (RoleDocument::query()->whereIn('_id', $ids)->get() as $document) {
            $map[(string) $document->getAttribute('_id')] = array_values((array) ($document->permissions ?? []));
        }

        return $map;
    }

    public function save(Role $role): void
    {
        ExplicitIdPersister::save($this->mapper->toDocument($role), (string) $role->id());
    }

    public function softDelete(RoleId $roleId): void
    {
        RoleDocument::query()->find((string) $roleId)?->delete();
    }
}
