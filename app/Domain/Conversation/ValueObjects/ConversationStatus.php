<?php

namespace App\Domain\Conversation\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\ValueObject;

final class ConversationStatus extends ValueObject
{
    public const ACTIVE = 'active';

    public const PAUSED = 'paused';

    public const HANDOFF = 'handoff';

    public const CLOSED = 'closed';

    private const ALLOWED = [self::ACTIVE, self::PAUSED, self::HANDOFF, self::CLOSED];

    public function __construct(public readonly string $value)
    {
        if (! in_array($value, self::ALLOWED, true)) {
            throw new DomainException("Unsupported conversation status [$value].");
        }
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function handoff(): self
    {
        return new self(self::HANDOFF);
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isHandoff(): bool
    {
        return $this->value === self::HANDOFF;
    }

    public function blocksAi(): bool
    {
        return in_array($this->value, [self::HANDOFF, self::PAUSED, self::CLOSED], true);
    }

    public function toPrimitives(): string
    {
        return $this->value;
    }
}
