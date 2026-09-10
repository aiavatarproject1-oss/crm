<?php

namespace App\Application\Observability\Contracts;

interface MetricsCollectorInterface
{
    /**
     * @param  array<string, scalar|null>  $tags
     */
    public function timing(string $name, float $milliseconds, array $tags = []): void;

    /**
     * @param  array<string, scalar|null>  $tags
     */
    public function increment(string $name, int $value = 1, array $tags = []): void;

    /**
     * @param  array<string, scalar|null>  $tags
     */
    public function gauge(string $name, float $value, array $tags = []): void;
}
