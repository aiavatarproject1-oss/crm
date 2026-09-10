<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\PersonaRepositoryInterface;
use App\Domain\Influencer\Entities\Persona;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\PersonaDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\PersonaMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;

final readonly class MongoPersonaRepository implements PersonaRepositoryInterface
{
    public function __construct(private PersonaMapper $mapper) {}

    public function findByInfluencer(TenantId $tenantId, InfluencerId $influencerId): ?Persona
    {
        $document = PersonaDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function save(Persona $persona): void
    {
        ExplicitIdPersister::save(
            $this->mapper->toDocument($persona),
            (string) $persona->id(),
            static fn () => PersonaDocument::query()
                ->where('tenant_id', (string) $persona->tenantId)
                ->where('influencer_id', (string) $persona->influencerId)
                ->first(),
        );
    }
}
