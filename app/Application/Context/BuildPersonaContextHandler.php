<?php

namespace App\Application\Context;

use App\Application\Context\DTO\PersonaContext;
use App\Application\Contracts\PersonaRepositoryInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Tenant\ValueObjects\TenantId;

final readonly class BuildPersonaContextHandler
{
    public function __construct(private PersonaRepositoryInterface $personas) {}

    public function handle(TenantId $tenantId, InfluencerId $influencerId): PersonaContext
    {
        $persona = $this->personas->findByInfluencer($tenantId, $influencerId);
        if ($persona === null) {
            return PersonaContext::fallback();
        }

        return new PersonaContext((string) $persona->id(), $persona->name, $persona->language, $persona->tone, $persona->style, $persona->description, $persona->systemRules, $persona->metadata);
    }
}
