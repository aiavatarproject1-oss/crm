<?php

namespace App\Domain\Message\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\ValueObjects\ValueObject;

final class MessagePlatform extends ValueObject
{
    public function __construct(public readonly string $value)
    {
        if (trim($value) === '') {
            throw new DomainException('Message platform cannot be empty.');
        }
    }

    public function toPrimitives(): string
    {
        return $this->value;
    }
}
