<?php

namespace App\Application\Knowledge\DTO;

final readonly class KnowledgeChunkData
{
    public function __construct(
        public string $content,
        public int $position,
        public int $tokenCount,
        public array $metadata = [],
    ) {}
}
