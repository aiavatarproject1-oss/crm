<?php

namespace App\Application\RAG\Strategies;

use App\Application\RAG\Contracts\RetrievalStrategyInterface;
use App\Application\RAG\Contracts\VectorStoreInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Tenant\ValueObjects\TenantId;

/**
 * Default strategy: delegate ranked cosine search to VectorStoreInterface.
 */
final readonly class VectorSimilarityRetrievalStrategy implements RetrievalStrategyInterface
{
    public function __construct(private VectorStoreInterface $vectors) {}

    public function search(
        TenantId $tenantId,
        InfluencerId $influencerId,
        array $queryVector,
        int $limit,
    ): array {
        return $this->vectors->search($tenantId, $influencerId, $queryVector, $limit);
    }
}
