<?php

namespace App\Domain\Memory\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\ValueObject;

final class MemoryStatus extends ValueObject
{
    public const ACTIVE = 'ACTIVE';

    public const ARCHIVED = 'ARCHIVED';

    public const SUPERSEDED = 'SUPERSEDED';

    private const ALLOWED = [self::ACTIVE, self::ARCHIVED, self::SUPERSEDED];

    public function __construct(public readonly string $value)
    {
        if (! in_array($value, self::ALLOWED, true)) {
            throw new DomainException("Unsupported memory status [$value].");
        }
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function archived(): self
    {
        return new self(self::ARCHIVED);
    }

    public static function superseded(): self
    {
        return new self(self::SUPERSEDED);
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->value === self::ARCHIVED;
    }

    public function isSuperseded(): bool
    {
        return $this->value === self::SUPERSEDED;
    }

    public function toPrimitives(): string
    {
        return $this->value;
    }
}
