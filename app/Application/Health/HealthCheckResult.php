<?php

namespace App\Application\Health;

final readonly class HealthCheckResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $name,
        public bool $healthy,
        public string $message,
        public float $latencyMs = 0.0,
        public array $meta = [],
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'healthy' => $this->healthy,
            'message' => $this->message,
            'latency_ms' => round($this->latencyMs, 2),
            'meta' => $this->meta,
        ];
    }
}
