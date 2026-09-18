<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeDocument;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\KnowledgeDocumentDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\KnowledgeDocumentMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;

final class MongoKnowledgeDocumentRepository implements KnowledgeDocumentRepositoryInterface
{
    public function __construct(private readonly KnowledgeDocumentMapper $mapper) {}

    public function findByInfluencer(TenantId $tenantId, InfluencerId $influencerId): array
    {
        return KnowledgeDocumentDocument::query()->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)->orderBy('document_id')->orderByDesc('version')->get()
            ->map(fn (KnowledgeDocumentDocument $document) => $this->mapper->toDomain($document))->all();
    }

    public function findVersion(TenantId $tenantId, InfluencerId $influencerId, KnowledgeDocumentId $documentId, int $version): ?KnowledgeDocument
    {
        $document = KnowledgeDocumentDocument::query()->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)->where('document_id', (string) $documentId)
            ->where('version', $version)->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findLatestVersion(TenantId $tenantId, InfluencerId $influencerId, KnowledgeDocumentId $documentId): ?KnowledgeDocument
    {
        $document = KnowledgeDocumentDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('document_id', (string) $documentId)
            ->orderByDesc('version')
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findByChecksum(TenantId $tenantId, InfluencerId $influencerId, string $checksum): ?KnowledgeDocument
    {
        if (trim($checksum) === '') {
            return null;
        }

        $document = KnowledgeDocumentDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('checksum', $checksum)
            ->orderByDesc('version')
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function save(KnowledgeDocument $document): void
    {
        $prototype = $this->mapper->toDocument($document);
        $id = (string) $prototype->getAttribute('_id');
        ExplicitIdPersister::save($prototype, $id);
    }
}
