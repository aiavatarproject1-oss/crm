<?php

namespace App\Domain\Quality\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\ValueObject;

final class ResponseDecision extends ValueObject
{
    public const APPROVED = 'APPROVED';

    public const REJECTED = 'REJECTED';

    public const ADMIN_REVIEW = 'ADMIN_REVIEW';

    public function __construct(public readonly string $value)
    {
        if (! in_array($value, [self::APPROVED, self::REJECTED, self::ADMIN_REVIEW], true)) {
            throw new DomainException("Unsupported response decision [$value].");
        }
    }

    public function toPrimitives(): string
    {
        return $this->value;
    }
}
