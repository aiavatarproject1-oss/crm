<?php

namespace App\Domain\Knowledge\Entities;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\ValueObjects\KnowledgeChunkId;
use App\Domain\Knowledge\ValueObjects\VectorRecordId;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Tenant\ValueObjects\TenantId;

final readonly class VectorRecord implements Entity
{
    /**
     * @param  list<float|int>  $vector
     */
    public function __construct(
        private VectorRecordId $vectorRecordId,
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public KnowledgeChunkId $chunkId,
        public array $vector,
        public int $dimensions,
        public string $embeddingModel,
        public string $contentHash,
        public array $metadata = [],
    ) {
        if (trim($embeddingModel) === '') {
            throw new DomainException('Vector embedding model cannot be empty.');
        }
        if (trim($contentHash) === '') {
            throw new DomainException('Vector content hash cannot be empty.');
        }
        if ($dimensions < 1 || count($vector) !== $dimensions) {
            throw new DomainException('Vector dimensions must match the embedding size.');
        }

        foreach ($vector as $value) {
            if (! is_int($value) && ! is_float($value)) {
                throw new DomainException('Vector embeddings may only contain numeric values.');
            }
        }
    }

    public function id(): VectorRecordId
    {
        return $this->vectorRecordId;
    }

    /** @deprecated Use $vector — kept for RAG helpers during transition. */
    public function embedding(): array
    {
        return $this->vector;
    }
}
