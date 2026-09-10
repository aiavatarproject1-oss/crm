<?php

namespace App\Application\RAG\DTO;

/**
 * Optional metadata filters applied after tenant/influencer scoping.
 */
final readonly class RetrievalFilter
{
    public function __construct(
        public ?string $documentId = null,
        public ?string $sourceId = null,
        public ?string $category = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->documentId === null
            && $this->sourceId === null
            && $this->category === null;
    }
}
