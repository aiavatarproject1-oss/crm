<?php

namespace App\Infrastructure\Observability;

use App\Application\Observability\Contracts\StructuredLoggerInterface;
use App\Application\Observability\CorrelationContext;
use Illuminate\Support\Facades\Log;

final readonly class LaravelStructuredLogger implements StructuredLoggerInterface
{
    public function __construct(private CorrelationContext $correlation) {}

    public function info(string $event, array $context = []): void
    {
        Log::info($event, $this->enrich($context));
    }

    public function warning(string $event, array $context = []): void
    {
        Log::warning($event, $this->enrich($context));
    }

    public function error(string $event, array $context = []): void
    {
        Log::error($event, $this->enrich($context));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function enrich(array $context): array
    {
        return [
            'correlation_id' => $this->correlation->get(),
            ...$this->redact($context),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        $blocked = ['api_key', 'password', 'token', 'authorization', 'secret', 'raw_payload', 'content', 'prompt', 'messages'];
        $clean = [];
        foreach ($context as $key => $value) {
            $lower = strtolower((string) $key);
            foreach ($blocked as $needle) {
                if (str_contains($lower, $needle)) {
                    continue 2;
                }
            }
            $clean[$key] = $value;
        }

        return $clean;
    }
}
