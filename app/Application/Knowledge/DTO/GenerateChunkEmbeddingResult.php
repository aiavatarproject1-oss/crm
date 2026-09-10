<?php

namespace App\Application\Knowledge\DTO;

use App\Domain\Knowledge\Entities\VectorRecord;

final readonly class GenerateChunkEmbeddingResult
{
    public function __construct(
        public VectorRecord $record,
        public bool $created,
        public bool $duplicate,
        public string $reason = '',
    ) {}
}
