<?php

namespace App\Domain\Knowledge\Entities;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\ValueObjects\KnowledgeChunkId;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Tenant\ValueObjects\TenantId;

final readonly class KnowledgeChunk implements Entity
{
    public function __construct(
        private KnowledgeChunkId $chunkId,
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public KnowledgeDocumentId $documentId,
        public string $content,
        public int $position,
        public int $tokenCount,
        public array $metadata = [],
        public int $documentVersion = 1,
    ) {
        if ($position < 0 || $tokenCount < 0) {
            throw new DomainException('Knowledge chunk position and token count cannot be negative.');
        }
        if ($documentVersion < 1) {
            throw new DomainException('Knowledge chunk document version must be at least 1.');
        }
    }

    public function id(): KnowledgeChunkId
    {
        return $this->chunkId;
    }
}
