<?php

namespace App\Domain\Admin\Entities;

use App\Domain\Admin\Permissions\Permission;
use App\Domain\Admin\ValueObjects\AdminId;
use App\Domain\Admin\ValueObjects\RoleId;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Exceptions\DomainException;
use DateTimeImmutable;

/**
 * Admin panel operator. Effective permissions = union(role permissions) ∪ direct permissions.
 * Super admins bypass every permission check.
 */
final class Admin implements Entity
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    /**
     * @param  list<RoleId>  $roleIds
     * @param  list<string>  $directPermissions
     */
    private function __construct(
        private readonly AdminId $adminId,
        private string $username,
        private string $name,
        private ?string $email,
        private string $passwordHash,
        private bool $isSuperAdmin,
        private array $roleIds,
        private array $directPermissions,
        private string $status,
        private string $locale,
        private ?DateTimeImmutable $lastLoginAt,
        private bool $mustChangePassword,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param  list<RoleId>  $roleIds
     * @param  list<string>  $directPermissions
     */
    public static function create(
        AdminId $adminId,
        string $username,
        string $name,
        ?string $email,
        string $passwordHash,
        bool $isSuperAdmin = false,
        array $roleIds = [],
        array $directPermissions = [],
        string $locale = 'en',
        bool $mustChangePassword = false,
        ?DateTimeImmutable $now = null,
    ): self {
        $now ??= new DateTimeImmutable;

        return new self(
            $adminId,
            self::normalizeUsername($username),
            trim($name),
            self::normalizeEmail($email),
            $passwordHash,
            $isSuperAdmin,
            array_values($roleIds),
            Permission::normalize($directPermissions),
            self::STATUS_ACTIVE,
            $locale,
            null,
            $mustChangePassword,
            $now,
            $now,
        );
    }

    /**
     * @param  list<RoleId>  $roleIds
     * @param  list<string>  $directPermissions
     */
    public static function restore(
        AdminId $adminId,
        string $username,
        string $name,
        ?string $email,
        string $passwordHash,
        bool $isSuperAdmin,
        array $roleIds,
        array $directPermissions,
        string $status,
        string $locale,
        ?DateTimeImmutable $lastLoginAt,
        bool $mustChangePassword,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self($adminId, $username, $name, $email, $passwordHash, $isSuperAdmin, array_values($roleIds), Permission::normalize($directPermissions), $status, $locale, $lastLoginAt, $mustChangePassword, $createdAt, $updatedAt);
    }

    public function id(): AdminId
    {
        return $this->adminId;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    /**
     * @return list<RoleId>
     */
    public function roleIds(): array
    {
        return $this->roleIds;
    }

    /**
     * @return list<string>
     */
    public function directPermissions(): array
    {
        return $this->directPermissions;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function lastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @param  array<string, list<string>>  $rolePermissions  role id => permissions
     * @return list<string>
     */
    public function effectivePermissions(array $rolePermissions): array
    {
        if ($this->isSuperAdmin) {
            return Permission::all();
        }

        $set = [];
        foreach ($this->roleIds as $roleId) {
            foreach ($rolePermissions[(string) $roleId] ?? [] as $permission) {
                $set[$permission] = true;
            }
        }
        foreach ($this->directPermissions as $permission) {
            $set[$permission] = true;
        }

        return Permission::normalize(array_keys($set));
    }

    public function rename(string $name, ?string $email, string $locale, ?DateTimeImmutable $now = null): void
    {
        $this->name = trim($name);
        $this->email = self::normalizeEmail($email);
        $this->locale = $locale;
        $this->touch($now);
    }

    /**
     * @param  list<RoleId>  $roleIds
     * @param  list<string>  $directPermissions
     */
    public function assignAccess(array $roleIds, array $directPermissions, bool $isSuperAdmin, ?DateTimeImmutable $now = null): void
    {
        $this->roleIds = array_values($roleIds);
        $this->directPermissions = Permission::normalize($directPermissions);
        $this->isSuperAdmin = $isSuperAdmin;
        $this->touch($now);
    }

    public function changePassword(string $newHash, bool $mustChangeOnNextLogin = false, ?DateTimeImmutable $now = null): void
    {
        $this->passwordHash = $newHash;
        $this->mustChangePassword = $mustChangeOnNextLogin;
        $this->touch($now);
    }

    public function suspend(?DateTimeImmutable $now = null): void
    {
        $this->status = self::STATUS_SUSPENDED;
        $this->touch($now);
    }

    public function activate(?DateTimeImmutable $now = null): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->touch($now);
    }

    public function recordLogin(?DateTimeImmutable $now = null): void
    {
        $this->lastLoginAt = $now ?? new DateTimeImmutable;
        $this->touch($this->lastLoginAt);
    }

    private function touch(?DateTimeImmutable $now): void
    {
        $this->updatedAt = $now ?? new DateTimeImmutable;
    }

    public static function normalizeUsername(string $username): string
    {
        $username = strtolower(trim($username));
        if ($username === '' || preg_match('/^[a-z0-9._-]{3,32}$/', $username) !== 1) {
            throw new DomainException('Username must be 3-32 chars: lowercase letters, digits, dot, underscore or dash.');
        }

        return $username;
    }

    private static function normalizeEmail(?string $email): ?string
    {
        $email = $email === null ? null : strtolower(trim($email));

        return $email === '' ? null : $email;
    }
}
