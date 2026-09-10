<?php

namespace App\Application\Memory\DTO;

use App\Domain\Memory\Entities\Memory;

final readonly class MemoryConsolidationDecision
{
    public const CREATE_NEW = 'CREATE_NEW';

    public const REJECT_DUPLICATE = 'REJECT_DUPLICATE';

    public const SUPERSEDE_EXISTING = 'SUPERSEDE_EXISTING';

    public function __construct(
        public string $strategy,
        public string $reason,
        public ?Memory $existingMemory = null,
        public ?Memory $resultingMemory = null,
        public array $metadata = [],
    ) {
        if (! in_array($strategy, [self::CREATE_NEW, self::REJECT_DUPLICATE, self::SUPERSEDE_EXISTING], true)) {
            throw new \InvalidArgumentException("Unsupported consolidation strategy [$strategy].");
        }
    }
}
