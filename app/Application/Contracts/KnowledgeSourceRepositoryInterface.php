<?php

namespace App\Application\Contracts;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeSource;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceId;
use App\Domain\Shared\Contracts\RepositoryInterface;
use App\Domain\Tenant\ValueObjects\TenantId;

interface KnowledgeSourceRepositoryInterface extends RepositoryInterface
{
    public function save(KnowledgeSource $source): void;

    public function findById(TenantId $tenantId, InfluencerId $influencerId, KnowledgeSourceId $sourceId): ?KnowledgeSource;

    public function findByChecksum(TenantId $tenantId, InfluencerId $influencerId, string $checksum): ?KnowledgeSource;
}
