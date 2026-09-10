<?php

namespace App\Application\Memory\DTO;

final readonly class MemoryRelation
{
    public const DUPLICATE = 'DUPLICATE';

    public const CONFLICT = 'CONFLICT';

    public const INDEPENDENT = 'INDEPENDENT';

    public function __construct(
        public string $value,
        public float $score = 0.0,
        public array $metadata = [],
    ) {
        if (! in_array($value, [self::DUPLICATE, self::CONFLICT, self::INDEPENDENT], true)) {
            throw new \InvalidArgumentException("Unsupported memory relation [$value].");
        }
    }

    public function isDuplicate(): bool
    {
        return $this->value === self::DUPLICATE;
    }

    public function isConflict(): bool
    {
        return $this->value === self::CONFLICT;
    }

    public function isIndependent(): bool
    {
        return $this->value === self::INDEPENDENT;
    }
}
