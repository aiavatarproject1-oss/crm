<?php

namespace App\Application\Memory\DTO;

final readonly class ExtractedMemoryCandidateData
{
    public function __construct(
        public string $type,
        public string $content,
        public float $confidence_score,
        public float $importance_score,
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'content' => $this->content,
            'confidence_score' => $this->confidence_score,
            'importance_score' => $this->importance_score,
            'metadata' => $this->metadata,
        ];
    }
}
