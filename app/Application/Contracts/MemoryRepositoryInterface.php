<?php

namespace App\Application\Contracts;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Memory\ValueObjects\MemoryId;
use App\Domain\Memory\ValueObjects\MemoryStatus;
use App\Domain\Memory\ValueObjects\MemoryType;
use App\Domain\Shared\Contracts\RepositoryInterface;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;

interface MemoryRepositoryInterface extends RepositoryInterface
{
    public function save(Memory $memory): void;

    public function findById(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, MemoryId $memoryId): ?Memory;

    /** @return list<Memory> */
    public function findActiveForUser(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, int $limit = 50): array;

    /** @return list<Memory> */
    public function findByType(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, MemoryType $type): array;

    /** @return list<Memory> */
    public function searchByScope(
        TenantId $tenantId,
        InfluencerId $influencerId,
        UserId $userId,
        ?MemoryStatus $status = null,
        ?MemoryType $type = null,
        int $limit = 100,
    ): array;

    /**
     * Context helper: active memories for a user, ordered by importance.
     *
     * @return list<Memory>
     */
    public function findImportantUserMemories(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, int $limit): array;
}
