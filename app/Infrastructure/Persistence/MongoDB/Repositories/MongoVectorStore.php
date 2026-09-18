<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\VectorRecordRepositoryInterface;
use App\Application\RAG\Contracts\VectorStoreInterface;
use App\Application\RAG\DTO\VectorSearchResult;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\VectorRecord;
use App\Domain\Knowledge\ValueObjects\KnowledgeChunkId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\VectorRecordDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\VectorRecordMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;

final class MongoVectorStore implements VectorRecordRepositoryInterface, VectorStoreInterface
{
    public function __construct(private readonly VectorRecordMapper $mapper) {}

    public function save(VectorRecord $record): void
    {
        $this->store($record);
    }

    public function store(VectorRecord $record): void
    {
        ExplicitIdPersister::save(
            $this->mapper->toDocument($record),
            (string) $record->id(),
            fn () => VectorRecordDocument::query()
                ->where('tenant_id', (string) $record->tenantId)
                ->where('influencer_id', (string) $record->influencerId)
                ->where('chunk_id', (string) $record->chunkId)
                ->where('embedding_model', $record->embeddingModel)
                ->where('content_hash', $record->contentHash)
                ->first(),
        );
    }

    public function findByIdempotencyKey(
        TenantId $tenantId,
        InfluencerId $influencerId,
        KnowledgeChunkId $chunkId,
        string $embeddingModel,
        string $contentHash,
    ): ?VectorRecord {
        $document = VectorRecordDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('chunk_id', (string) $chunkId)
            ->where('embedding_model', $embeddingModel)
            ->where('content_hash', $contentHash)
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findByChunk(
        TenantId $tenantId,
        InfluencerId $influencerId,
        KnowledgeChunkId $chunkId,
    ): array {
        return VectorRecordDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('chunk_id', (string) $chunkId)
            ->get()
            ->map(fn (VectorRecordDocument $document) => $this->mapper->toDomain($document))
            ->all();
    }

    public function search(TenantId $tenantId, InfluencerId $influencerId, array $queryVector, int $limit): array
    {
        if ($limit < 1 || $queryVector === []) {
            return [];
        }

        $records = VectorRecordDocument::query()->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)->get()
            ->map(fn (VectorRecordDocument $document) => $this->mapper->toDomain($document))->all();

        return $this->rank($records, $queryVector, $limit);
    }

    /**
     * @param  list<VectorRecord>  $records
     * @return list<VectorSearchResult>
     */
    public function rank(array $records, array $queryVector, int $limit): array
    {
        $results = [];
        foreach ($records as $record) {
            if ($record->dimensions !== count($queryVector)) {
                continue;
            }
            $results[] = new VectorSearchResult($record, $this->cosineSimilarity($record->vector, $queryVector));
        }
        usort($results, fn (VectorSearchResult $left, VectorSearchResult $right): int => $right->score <=> $left->score);

        return array_slice($results, 0, max(0, $limit));
    }

    private function cosineSimilarity(array $left, array $right): float
    {
        $dot = $leftNorm = $rightNorm = 0.0;
        foreach ($left as $index => $value) {
            $dot += $value * $right[$index];
            $leftNorm += $value * $value;
            $rightNorm += $right[$index] * $right[$index];
        }

        return $leftNorm == 0.0 || $rightNorm == 0.0 ? 0.0 : $dot / (sqrt($leftNorm) * sqrt($rightNorm));
    }
}
