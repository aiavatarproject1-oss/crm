<?php

namespace App\Application\Contracts;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeChunk;
use App\Domain\Knowledge\ValueObjects\KnowledgeChunkId;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Shared\Contracts\RepositoryInterface;
use App\Domain\Tenant\ValueObjects\TenantId;

interface KnowledgeChunkRepositoryInterface extends RepositoryInterface
{
    /** @return list<KnowledgeChunk> */
    public function findByDocument(TenantId $tenantId, InfluencerId $influencerId, KnowledgeDocumentId $documentId): array;

    /** @param list<KnowledgeChunkId> $chunkIds
     * @return list<KnowledgeChunk>
     */
    public function findByIds(TenantId $tenantId, InfluencerId $influencerId, array $chunkIds): array;

    public function save(KnowledgeChunk $chunk): void;
}
