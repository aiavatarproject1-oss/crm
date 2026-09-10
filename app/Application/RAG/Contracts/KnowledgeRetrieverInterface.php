<?php

namespace App\Application\RAG\Contracts;

use App\Application\RAG\DTO\KnowledgeSearchResult;
use App\Application\RAG\DTO\RetrievalFilter;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Tenant\ValueObjects\TenantId;

interface KnowledgeRetrieverInterface
{
    /**
     * @return list<KnowledgeSearchResult>
     */
    public function retrieve(
        TenantId $tenantId,
        InfluencerId $influencerId,
        string $query,
        int $limit,
        ?RetrievalFilter $filter = null,
    ): array;
}
