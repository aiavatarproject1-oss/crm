<?php

namespace App\Infrastructure\Observability;

use App\Application\Observability\Contracts\MetricsCollectorInterface;
use App\Application\Observability\Contracts\StructuredLoggerInterface;
use App\Application\Observability\CorrelationContext;

/**
 * Metrics foundation: emit structured metric events (swap later for Prometheus/StatsD).
 */
final class StructuredLogMetricsCollector implements MetricsCollectorInterface
{
    /** @var list<array{type: string, name: string, value: float|int, tags: array<string, scalar|null>}> */
    public array $recorded = [];

    public function __construct(
        private StructuredLoggerInterface $logger,
        private CorrelationContext $correlation,
    ) {}

    public function timing(string $name, float $milliseconds, array $tags = []): void
    {
        $this->record('timing', $name, $milliseconds, $tags);
        $this->logger->info('metric.timing', [
            'metric' => $name,
            'value_ms' => round($milliseconds, 2),
            'tags' => $tags,
            'correlation_id' => $this->correlation->get(),
        ]);
    }

    public function increment(string $name, int $value = 1, array $tags = []): void
    {
        $this->record('increment', $name, $value, $tags);
        $this->logger->info('metric.increment', [
            'metric' => $name,
            'value' => $value,
            'tags' => $tags,
            'correlation_id' => $this->correlation->get(),
        ]);
    }

    public function gauge(string $name, float $value, array $tags = []): void
    {
        $this->record('gauge', $name, $value, $tags);
        $this->logger->info('metric.gauge', [
            'metric' => $name,
            'value' => $value,
            'tags' => $tags,
            'correlation_id' => $this->correlation->get(),
        ]);
    }

    /**
     * @param  array<string, scalar|null>  $tags
     */
    private function record(string $type, string $name, float|int $value, array $tags): void
    {
        $this->recorded[] = [
            'type' => $type,
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
        ];
    }
}
