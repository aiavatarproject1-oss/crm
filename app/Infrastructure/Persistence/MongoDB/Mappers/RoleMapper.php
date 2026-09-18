<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Admin\Entities\Role;
use App\Domain\Admin\ValueObjects\RoleId;
use App\Infrastructure\Persistence\MongoDB\Documents\RoleDocument;
use DateTimeImmutable;

final class RoleMapper
{
    public function toDocument(Role $role): RoleDocument
    {
        $document = new RoleDocument([
            'slug' => $role->slug,
            'name' => $role->name(),
            'description' => $role->description(),
            'permissions' => $role->permissions(),
            'is_system' => $role->isSystem,
            'created_at' => $role->createdAt,
            'updated_at' => $role->updatedAt(),
        ]);
        $document->setAttribute('_id', (string) $role->id());

        return $document;
    }

    public function toDomain(RoleDocument $document): Role
    {
        return Role::restore(
            new RoleId((string) $document->getAttribute('_id')),
            (string) $document->slug,
            (string) $document->name,
            (string) ($document->description ?? ''),
            (array) ($document->permissions ?? []),
            (bool) ($document->is_system ?? false),
            new DateTimeImmutable((string) $document->created_at),
            new DateTimeImmutable((string) ($document->updated_at ?? $document->created_at)),
        );
    }
}
