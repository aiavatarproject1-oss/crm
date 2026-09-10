<?php

namespace App\Application\RAG\DTO;

use App\Domain\Knowledge\Entities\KnowledgeChunk;

final readonly class RetrievedKnowledgeChunk
{
    public function __construct(public KnowledgeChunk $chunk, public float $score, public array $metadata = []) {}

    public function toArray(): array
    {
        return [
            'chunk_id' => (string) $this->chunk->id(),
            'document_id' => (string) $this->chunk->documentId,
            'content' => $this->chunk->content,
            'position' => $this->chunk->position,
            'score' => $this->score,
            'metadata' => [...$this->chunk->metadata, ...$this->metadata],
        ];
    }
}
