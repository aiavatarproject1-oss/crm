<?php

namespace App\Application\RAG\Contracts;

use App\Application\RAG\DTO\VectorSearchResult;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\VectorRecord;
use App\Domain\Tenant\ValueObjects\TenantId;

interface VectorStoreInterface
{
    public function store(VectorRecord $record): void;

    /** @return list<VectorSearchResult> */
    public function search(TenantId $tenantId, InfluencerId $influencerId, array $queryVector, int $limit): array;
}
