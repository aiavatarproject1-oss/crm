<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Influencer\Entities\Persona;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Influencer\ValueObjects\PersonaId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\PersonaDocument;

final class PersonaMapper
{
    public function toDocument(Persona $persona): PersonaDocument
    {
        $document = new PersonaDocument([
            'tenant_id' => (string) $persona->tenantId,
            'influencer_id' => (string) $persona->influencerId,
            'name' => $persona->name,
            'language' => $persona->language,
            'tone' => $persona->tone,
            'style' => $persona->style,
            'description' => $persona->description,
            'system_rules' => $persona->systemRules,
            'metadata' => $persona->metadata,
        ]);
        $document->setAttribute('_id', (string) $persona->id());

        return $document;
    }

    public function toDomain(PersonaDocument $document): Persona
    {
        return new Persona(
            new PersonaId((string) $document->getAttribute('_id')),
            new TenantId((string) $document->tenant_id),
            new InfluencerId((string) $document->influencer_id),
            (string) $document->name,
            (string) $document->language,
            (string) $document->tone,
            (string) $document->style,
            (string) $document->description,
            (array) $document->system_rules,
            (array) ($document->metadata ?? []),
        );
    }
}
