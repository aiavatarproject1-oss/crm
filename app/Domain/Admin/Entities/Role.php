<?php

namespace App\Domain\Admin\Entities;

use App\Domain\Admin\Permissions\Permission;
use App\Domain\Admin\ValueObjects\RoleId;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Exceptions\DomainException;
use DateTimeImmutable;

final class Role implements Entity
{
    public const SUPER_ADMIN_SLUG = 'super-admin';

    /**
     * @param  list<string>  $permissions
     */
    private function __construct(
        private readonly RoleId $roleId,
        public readonly string $slug,
        private string $name,
        private string $description,
        private array $permissions,
        public readonly bool $isSystem,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param  list<string>  $permissions
     */
    public static function create(
        RoleId $roleId,
        string $slug,
        string $name,
        string $description,
        array $permissions,
        bool $isSystem = false,
        ?DateTimeImmutable $now = null,
    ): self {
        $slug = self::normalizeSlug($slug);
        $now ??= new DateTimeImmutable;

        return new self($roleId, $slug, trim($name), trim($description), Permission::normalize($permissions), $isSystem, $now, $now);
    }

    /**
     * @param  list<string>  $permissions
     */
    public static function restore(
        RoleId $roleId,
        string $slug,
        string $name,
        string $description,
        array $permissions,
        bool $isSystem,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self($roleId, $slug, $name, $description, Permission::normalize($permissions), $isSystem, $createdAt, $updatedAt);
    }

    public function id(): RoleId
    {
        return $this->roleId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === self::SUPER_ADMIN_SLUG;
    }

    /**
     * @param  list<string>  $permissions
     */
    public function update(string $name, string $description, array $permissions, ?DateTimeImmutable $now = null): void
    {
        if ($this->isSuperAdmin()) {
            throw new DomainException('The super-admin role cannot be modified.');
        }

        $this->name = trim($name);
        $this->description = trim($description);
        $this->permissions = Permission::normalize($permissions);
        $this->updatedAt = $now ?? new DateTimeImmutable;
    }

    public static function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '') {
            throw new DomainException('Role slug cannot be empty.');
        }

        return $slug;
    }
}
