<?php

namespace App\Domain\Influencer\Entities;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Influencer\ValueObjects\PersonaId;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Tenant\ValueObjects\TenantId;

final readonly class Persona implements Entity
{
    public function __construct(
        private PersonaId $personaId,
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public string $name,
        public string $language,
        public string $tone,
        public string $style,
        public string $description,
        public array $systemRules,
        public array $metadata = [],
    ) {}

    public function id(): PersonaId
    {
        return $this->personaId;
    }
}
