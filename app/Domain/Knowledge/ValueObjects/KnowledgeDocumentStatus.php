<?php

namespace App\Domain\Knowledge\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\ValueObject;

final class KnowledgeDocumentStatus extends ValueObject
{
    public const DRAFT = 'DRAFT';

    public const ACTIVE = 'ACTIVE';

    public const ARCHIVED = 'ARCHIVED';

    private const ALLOWED = [self::DRAFT, self::ACTIVE, self::ARCHIVED];

    public readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper($value);
        // Legacy lowercase "active" maps to ACTIVE.
        if ($normalized === 'ACTIVE' || $value === 'active') {
            $normalized = self::ACTIVE;
        }

        if (! in_array($normalized, self::ALLOWED, true)) {
            throw new DomainException("Unsupported knowledge document status [$value].");
        }

        $this->value = $normalized;
    }

    public static function draft(): self
    {
        return new self(self::DRAFT);
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function archived(): self
    {
        return new self(self::ARCHIVED);
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function toPrimitives(): string
    {
        return $this->value;
    }
}
