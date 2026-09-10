<?php

namespace App\Domain\AI\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\ValueObject;

final class AiProcessingTaskStatus extends ValueObject
{
    public const PENDING = 'PENDING';

    public const PROCESSING = 'PROCESSING';

    public const COMPLETED = 'COMPLETED';

    public const FAILED = 'FAILED';

    public const RETRYING = 'RETRYING';

    private const ALLOWED = [
        self::PENDING,
        self::PROCESSING,
        self::COMPLETED,
        self::FAILED,
        self::RETRYING,
    ];

    public function __construct(public readonly string $value)
    {
        if (! in_array($value, self::ALLOWED, true)) {
            throw new DomainException("Unsupported AI processing task status [$value].");
        }
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function processing(): self
    {
        return new self(self::PROCESSING);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public static function retrying(): self
    {
        return new self(self::RETRYING);
    }

    public function isCompleted(): bool
    {
        return $this->value === self::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->value === self::FAILED;
    }

    public function isTerminal(): bool
    {
        return $this->isCompleted() || $this->isFailed();
    }

    public function toPrimitives(): string
    {
        return $this->value;
    }
}
