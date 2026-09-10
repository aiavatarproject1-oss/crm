<?php

namespace App\Domain\Knowledge\Entities;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentStatus;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceId;
use App\Domain\Knowledge\ValueObjects\KnowledgeType;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Tenant\ValueObjects\TenantId;

final readonly class KnowledgeDocument implements Entity
{
    public KnowledgeDocumentStatus $status;

    public function __construct(
        private KnowledgeDocumentId $documentId,
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public KnowledgeType $type,
        public string $title,
        public string $content,
        public int $version,
        KnowledgeDocumentStatus|string $status,
        public string $source,
        public array $metadata = [],
        public ?KnowledgeSourceId $sourceId = null,
        public ?string $checksum = null,
    ) {
        if ($version < 1) {
            throw new DomainException('Knowledge document version must be at least 1.');
        }

        $this->status = $status instanceof KnowledgeDocumentStatus
            ? $status
            : new KnowledgeDocumentStatus((string) $status);
    }

    public function id(): KnowledgeDocumentId
    {
        return $this->documentId;
    }
}
