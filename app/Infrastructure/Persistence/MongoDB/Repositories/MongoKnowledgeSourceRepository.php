<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\KnowledgeSourceRepositoryInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeSource;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\KnowledgeSourceDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\KnowledgeSourceMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;

final class MongoKnowledgeSourceRepository implements KnowledgeSourceRepositoryInterface
{
    public function __construct(private readonly KnowledgeSourceMapper $mapper) {}

    public function save(KnowledgeSource $source): void
    {
        ExplicitIdPersister::save(
            $this->mapper->toDocument($source),
            (string) $source->id(),
        );
    }

    public function findById(TenantId $tenantId, InfluencerId $influencerId, KnowledgeSourceId $sourceId): ?KnowledgeSource
    {
        $document = KnowledgeSourceDocument::query()
            ->where('_id', (string) $sourceId)
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findByChecksum(TenantId $tenantId, InfluencerId $influencerId, string $checksum): ?KnowledgeSource
    {
        $document = KnowledgeSourceDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('checksum', $checksum)
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }
}
