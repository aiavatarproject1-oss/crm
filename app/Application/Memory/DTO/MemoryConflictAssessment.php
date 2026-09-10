<?php

namespace App\Application\Memory\DTO;

use App\Domain\Memory\Entities\Memory;

final readonly class MemoryConflictAssessment
{
    public function __construct(
        public MemoryRelation $relation,
        public ?Memory $relatedMemory = null,
        public string $reason = '',
        public array $metadata = [],
    ) {}
}
