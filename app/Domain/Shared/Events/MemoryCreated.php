<?php

namespace App\Domain\Shared\Events;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\ValueObjects\MemoryId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;

final readonly class MemoryCreated
{
    public function __construct(
        public MemoryId $memoryId,
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public UserId $userId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
