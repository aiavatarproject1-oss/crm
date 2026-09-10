<?php

namespace App\Application\Observability;

/**
 * Request/job scoped correlation id for log and metric propagation.
 */
final class CorrelationContext
{
    private ?string $correlationId = null;

    public function set(string $correlationId): void
    {
        $trimmed = trim($correlationId);
        $this->correlationId = $trimmed === '' ? null : $trimmed;
    }

    public function get(): ?string
    {
        return $this->correlationId;
    }

    public function id(): string
    {
        if ($this->correlationId === null) {
            $this->correlationId = bin2hex(random_bytes(16));
        }

        return $this->correlationId;
    }

    public function clear(): void
    {
        $this->correlationId = null;
    }
}
