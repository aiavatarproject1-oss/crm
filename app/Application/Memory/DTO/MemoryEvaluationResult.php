<?php

namespace App\Application\Memory\DTO;

final readonly class MemoryEvaluationResult
{
    public const APPROVE = 'APPROVE';

    public const REJECT = 'REJECT';

    /**
     * @param  list<string>  $issues
     */
    public function __construct(
        public string $decision,
        public string $reason,
        public array $issues = [],
        public array $metadata = [],
    ) {
        if (! in_array($decision, [self::APPROVE, self::REJECT], true)) {
            throw new \InvalidArgumentException("Unsupported memory evaluation decision [$decision].");
        }
    }

    public function approved(): bool
    {
        return $this->decision === self::APPROVE;
    }

    public function rejected(): bool
    {
        return $this->decision === self::REJECT;
    }
}
