<?php

namespace App\Domain\Influencer\Entities;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Tenant\ValueObjects\TenantId;

final readonly class Influencer implements Entity
{
    public function __construct(
        private InfluencerId $influencerId,
        public TenantId $tenantId,
        public string $name,
        public string $persona,
        public string $language,
        public string $style,
        public array $behaviorConfiguration,
    ) {}

    public function id(): InfluencerId
    {
        return $this->influencerId;
    }
}
