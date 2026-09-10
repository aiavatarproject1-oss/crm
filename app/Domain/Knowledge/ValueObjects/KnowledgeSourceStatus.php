<?php

namespace App\Domain\Knowledge\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\ValueObject;

final class KnowledgeSourceStatus extends ValueObject
{
    public const PENDING = 'PENDING';

    public const PARSED = 'PARSED';

    public const INGESTED = 'INGESTED';

    public const FAILED = 'FAILED';

    private const ALLOWED = [self::PENDING, self::PARSED, self::INGESTED, self::FAILED];

    public function __construct(public readonly string $value)
    {
        if (! in_array($value, self::ALLOWED, true)) {
            throw new DomainException("Unsupported knowledge source status [$value].");
        }
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function parsed(): self
    {
        return new self(self::PARSED);
    }

    public static function ingested(): self
    {
        return new self(self::INGESTED);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public function toPrimitives(): string
    {
        return $this->value;
    }
}
