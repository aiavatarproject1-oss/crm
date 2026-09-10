<?php

namespace App\Domain\Memory\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\ValueObject;

final class MemoryType extends ValueObject
{
    public const PROFILE = 'PROFILE';

    public const PREFERENCE = 'PREFERENCE';

    public const FACT = 'FACT';

    public const RELATIONSHIP = 'RELATIONSHIP';

    public const EVENT = 'EVENT';

    public const GOAL = 'GOAL';

    private const ALLOWED = [
        self::PROFILE,
        self::PREFERENCE,
        self::FACT,
        self::RELATIONSHIP,
        self::EVENT,
        self::GOAL,
    ];

    public function __construct(public readonly string $value)
    {
        if (! in_array($value, self::ALLOWED, true)) {
            throw new DomainException("Unsupported memory type [$value].");
        }
    }

    public function toPrimitives(): string
    {
        return $this->value;
    }
}
