<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Admin\Entities\Admin;
use App\Domain\Admin\ValueObjects\AdminId;
use App\Domain\Admin\ValueObjects\RoleId;
use App\Infrastructure\Persistence\MongoDB\Documents\AdminDocument;
use DateTimeImmutable;

final class AdminMapper
{
    public function toDocument(Admin $admin): AdminDocument
    {
        $document = new AdminDocument([
            'username' => $admin->username(),
            'name' => $admin->name(),
            'email' => $admin->email(),
            'password_hash' => $admin->passwordHash(),
            'is_super_admin' => $admin->isSuperAdmin(),
            'role_ids' => array_map('strval', $admin->roleIds()),
            'direct_permissions' => $admin->directPermissions(),
            'status' => $admin->status(),
            'locale' => $admin->locale(),
            'last_login_at' => $admin->lastLoginAt(),
            'must_change_password' => $admin->mustChangePassword(),
            'created_at' => $admin->createdAt,
            'updated_at' => $admin->updatedAt(),
        ]);
        $document->setAttribute('_id', (string) $admin->id());

        return $document;
    }

    public function toDomain(AdminDocument $document): Admin
    {
        $lastLogin = $document->last_login_at;

        return Admin::restore(
            new AdminId((string) $document->getAttribute('_id')),
            (string) $document->username,
            (string) $document->name,
            $document->email === null ? null : (string) $document->email,
            (string) $document->password_hash,
            (bool) $document->is_super_admin,
            array_map(static fn ($id): RoleId => new RoleId((string) $id), (array) ($document->role_ids ?? [])),
            (array) ($document->direct_permissions ?? []),
            (string) ($document->status ?? Admin::STATUS_ACTIVE),
            (string) ($document->locale ?? 'en'),
            $lastLogin === null ? null : new DateTimeImmutable((string) $lastLogin),
            (bool) ($document->must_change_password ?? false),
            new DateTimeImmutable((string) $document->created_at),
            new DateTimeImmutable((string) ($document->updated_at ?? $document->created_at)),
        );
    }
}
