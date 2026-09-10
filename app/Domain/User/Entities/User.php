<?php

namespace App\Domain\User\Entities;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;

final readonly class User implements Entity
{
    public function __construct(
        private UserId $userId,
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public string $platform,
        public string $externalUserId,
        public ?string $username,
        public ?string $language,
    ) {}

    public function id(): UserId
    {
        return $this->userId;
    }
}
