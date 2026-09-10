<?php

namespace App\Application\Contracts;

use App\Domain\Influencer\Entities\Persona;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Shared\Contracts\RepositoryInterface;
use App\Domain\Tenant\ValueObjects\TenantId;

interface PersonaRepositoryInterface extends RepositoryInterface
{
    public function findByInfluencer(TenantId $tenantId, InfluencerId $influencerId): ?Persona;

    public function save(Persona $persona): void;
}
