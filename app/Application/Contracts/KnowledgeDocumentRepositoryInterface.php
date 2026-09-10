<?php

namespace App\Application\Contracts;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeDocument;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Shared\Contracts\RepositoryInterface;
use App\Domain\Tenant\ValueObjects\TenantId;

interface KnowledgeDocumentRepositoryInterface extends RepositoryInterface
{
    /** @return list<KnowledgeDocument> */
    public function findByInfluencer(TenantId $tenantId, InfluencerId $influencerId): array;

    public function findVersion(TenantId $tenantId, InfluencerId $influencerId, KnowledgeDocumentId $documentId, int $version): ?KnowledgeDocument;

    public function findLatestVersion(TenantId $tenantId, InfluencerId $influencerId, KnowledgeDocumentId $documentId): ?KnowledgeDocument;

    public function findByChecksum(TenantId $tenantId, InfluencerId $influencerId, string $checksum): ?KnowledgeDocument;

    public function save(KnowledgeDocument $document): void;
}
