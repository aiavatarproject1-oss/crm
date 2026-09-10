<?php

namespace App\Domain\Rule\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\ValueObject;

final class RuleDecision extends ValueObject
{
    public const ALLOW_AI = 'ALLOW_AI';

    public const ADMIN_REVIEW = 'ADMIN_REVIEW';

    public const BLOCK = 'BLOCK';

    public const IGNORE = 'IGNORE';

    public function __construct(public readonly string $value)
    {
        if (! in_array($value, [self::ALLOW_AI, self::ADMIN_REVIEW, self::BLOCK, self::IGNORE], true)) {
            throw new DomainException("Unsupported rule decision [$value].");
        }
    }

    public static function allowAi(): self
    {
        return new self(self::ALLOW_AI);
    }

    public function toPrimitives(): string
    {
        return $this->value;
    }
}
