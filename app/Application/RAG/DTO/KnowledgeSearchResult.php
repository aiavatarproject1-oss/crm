<?php

namespace App\Application\RAG\DTO;

/**
 * Application-facing retrieval hit. Does not expose VectorRecord.
 */
final readonly class KnowledgeSearchResult
{
    public function __construct(
        public string $chunkId,
        public string $content,
        public float $score,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'chunk_id' => $this->chunkId,
            'content' => $this->content,
            'score' => $this->score,
            'metadata' => $this->metadata,
        ];
    }
}
