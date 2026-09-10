<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\KnowledgeChunkRepositoryInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeChunk;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\KnowledgeChunkDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\KnowledgeChunkMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;
use MongoDB\Collection;

final class MongoKnowledgeChunkRepository implements KnowledgeChunkRepositoryInterface
{
    private bool $indexesEnsured = false;

    public function __construct(private readonly KnowledgeChunkMapper $mapper) {}

    public function findByDocument(TenantId $tenantId, InfluencerId $influencerId, KnowledgeDocumentId $documentId): array
    {
        $this->ensureIndexes();

        return KnowledgeChunkDocument::query()->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)->where('document_id', (string) $documentId)
            ->orderBy('position')->get()->map(fn (KnowledgeChunkDocument $document) => $this->mapper->toDomain($document))->all();
    }

    public function save(KnowledgeChunk $chunk): void
    {
        $this->ensureIndexes();
        ExplicitIdPersister::save($this->mapper->toDocument($chunk), (string) $chunk->id());
    }

    public function findByIds(TenantId $tenantId, InfluencerId $influencerId, array $chunkIds): array
    {
        if ($chunkIds === []) {
            return [];
        }

        $this->ensureIndexes();

        return KnowledgeChunkDocument::query()->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->whereIn('_id', array_map(static fn ($id): string => (string) $id, $chunkIds))
            ->get()->map(fn (KnowledgeChunkDocument $document) => $this->mapper->toDomain($document))->all();
    }

    private function ensureIndexes(): void
    {
        if ($this->indexesEnsured) {
            return;
        }

        KnowledgeChunkDocument::raw(static function (Collection $collection): void {
            $collection->createIndex(
                ['tenant_id' => 1, 'influencer_id' => 1, 'document_id' => 1, 'position' => 1],
                ['name' => 'knowledge_chunk_scope_position']
            );
        });
        $this->indexesEnsured = true;
    }
}
