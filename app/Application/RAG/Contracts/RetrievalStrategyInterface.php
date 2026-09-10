<?php

namespace App\Application\RAG\Contracts;

use App\Application\RAG\DTO\VectorSearchResult;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Tenant\ValueObjects\TenantId;

/**
 * Replaceable vector-search strategy (Mongo cosine today; Qdrant later).
 */
interface RetrievalStrategyInterface
{
    /**
     * @param  list<float|int>  $queryVector
     * @return list<VectorSearchResult>
     */
    public function search(
        TenantId $tenantId,
        InfluencerId $influencerId,
        array $queryVector,
        int $limit,
    ): array;
}
