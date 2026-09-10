<?php

namespace App\Domain\Rule\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\ValueObject;

final class RuleType extends ValueObject
{
    public const KEYWORD = 'KEYWORD';

    public const REGEX = 'REGEX';

    public function __construct(public readonly string $value)
    {
        if (! in_array($value, [self::KEYWORD, self::REGEX], true)) {
            throw new DomainException("Unsupported rule type [$value].");
        }
    }

    public function toPrimitives(): string
    {
        return $this->value;
    }
}
