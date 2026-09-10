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
use MongoDB\Collection;

final class MongoKnowledgeDocumentRepository implements KnowledgeDocumentRepositoryInterface
{
    private bool $indexesEnsured = false;

    public function __construct(private readonly KnowledgeDocumentMapper $mapper) {}

    public function findByInfluencer(TenantId $tenantId, InfluencerId $influencerId): array
    {
        $this->ensureIndexes();

        return KnowledgeDocumentDocument::query()->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)->orderBy('document_id')->orderByDesc('version')->get()
            ->map(fn (KnowledgeDocumentDocument $document) => $this->mapper->toDomain($document))->all();
    }

    public function findVersion(TenantId $tenantId, InfluencerId $influencerId, KnowledgeDocumentId $documentId, int $version): ?KnowledgeDocument
    {
        $this->ensureIndexes();
        $document = KnowledgeDocumentDocument::query()->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)->where('document_id', (string) $documentId)
            ->where('version', $version)->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findLatestVersion(TenantId $tenantId, InfluencerId $influencerId, KnowledgeDocumentId $documentId): ?KnowledgeDocument
    {
        $this->ensureIndexes();
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
        $this->ensureIndexes();
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
        $this->ensureIndexes();
        $prototype = $this->mapper->toDocument($document);
        $id = (string) $prototype->getAttribute('_id');
        ExplicitIdPersister::save($prototype, $id);
    }

    private function ensureIndexes(): void
    {
        if ($this->indexesEnsured) {
            return;
        }

        KnowledgeDocumentDocument::raw(static function (Collection $collection): void {
            $collection->createIndexes([
                ['key' => ['tenant_id' => 1, 'influencer_id' => 1, 'type' => 1, 'version' => -1], 'name' => 'knowledge_scope_type_version'],
                ['key' => ['tenant_id' => 1, 'influencer_id' => 1, 'document_id' => 1, 'version' => 1], 'name' => 'knowledge_document_version_unique', 'unique' => true],
                ['key' => ['tenant_id' => 1, 'influencer_id' => 1, 'checksum' => 1], 'name' => 'knowledge_scope_checksum'],
            ]);
        });
        $this->indexesEnsured = true;
    }
}
