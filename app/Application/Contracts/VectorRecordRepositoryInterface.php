<?php

namespace App\Application\Contracts;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\VectorRecord;
use App\Domain\Knowledge\ValueObjects\KnowledgeChunkId;
use App\Domain\Shared\Contracts\RepositoryInterface;
use App\Domain\Tenant\ValueObjects\TenantId;

interface VectorRecordRepositoryInterface extends RepositoryInterface
{
    public function save(VectorRecord $record): void;

    public function findByIdempotencyKey(
        TenantId $tenantId,
        InfluencerId $influencerId,
        KnowledgeChunkId $chunkId,
        string $embeddingModel,
        string $contentHash,
    ): ?VectorRecord;

    /** @return list<VectorRecord> */
    public function findByChunk(
        TenantId $tenantId,
        InfluencerId $influencerId,
        KnowledgeChunkId $chunkId,
    ): array;
}
