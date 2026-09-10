<?php

namespace App\Application\Contracts;

use App\Domain\Shared\Contracts\RepositoryInterface;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserId;

interface UserRepositoryInterface extends RepositoryInterface
{
    public function find(UserId $userId): ?User;

    /**
     * Resolve a user by stable external identity within a tenant.
     *
     * Uniqueness: tenant_id + platform + platform_user_id
     * Influencer is intentionally excluded so the same external person
     * maps to one User across influencers inside the same tenant.
     */
    public function findByPlatformIdentity(TenantId $tenantId, string $platform, string $externalUserId): ?User;

    public function save(User $user): void;
}
