<?php

namespace App\Domain\Shared\ValueObjects;

abstract class ValueObject
{
    abstract public function toPrimitives(): mixed;

    final public function equals(self $other): bool
    {
        return $this::class === $other::class
            && $this->toPrimitives() === $other->toPrimitives();
    }
}
