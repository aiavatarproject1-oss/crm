<?php

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\Exceptions\DomainException;

abstract class Identifier extends ValueObject
{
    final public function __construct(public readonly string $value)
    {
        if (trim($value) === '') {
            throw new DomainException('An identifier cannot be empty.');
        }
    }

    final public function toPrimitives(): string
    {
        return $this->value;
    }

    final public function __toString(): string
    {
        return $this->value;
    }
}
