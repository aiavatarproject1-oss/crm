<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeSource;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceId;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceStatus;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceType;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\KnowledgeSourceDocument;
use DateTimeImmutable;

final class KnowledgeSourceMapper
{
    public function toDocument(KnowledgeSource $source): KnowledgeSourceDocument
    {
        $document = new KnowledgeSourceDocument([
            'tenant_id' => (string) $source->tenantId,
            'influencer_id' => (string) $source->influencerId,
            'type' => $source->type->value,
            'name' => $source->name,
            'checksum' => $source->checksum,
            'payload' => $source->payload,
            'status' => $source->status()->value,
            'metadata' => $source->metadata,
            'created_at' => $source->createdAt,
            'updated_at' => $source->updatedAt(),
        ]);
        $document->setAttribute('_id', (string) $source->id());

        return $document;
    }

    public function toDomain(KnowledgeSourceDocument $document): KnowledgeSource
    {
        return KnowledgeSource::reconstitute(
            new KnowledgeSourceId((string) $document->getAttribute('_id')),
            new TenantId((string) $document->tenant_id),
            new InfluencerId((string) $document->influencer_id),
            new KnowledgeSourceType((string) $document->type),
            (string) $document->name,
            (string) $document->checksum,
            (string) $document->payload,
            new KnowledgeSourceStatus((string) $document->status),
            (array) ($document->metadata ?? []),
            $document->created_at instanceof DateTimeImmutable ? $document->created_at : new DateTimeImmutable((string) $document->created_at),
            $document->updated_at instanceof DateTimeImmutable ? $document->updated_at : new DateTimeImmutable((string) $document->updated_at),
        );
    }
}
