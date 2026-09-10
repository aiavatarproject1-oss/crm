<?php

namespace App\Application\AI\Commands;

use App\Application\AI\Contracts\LlmGatewayInterface;
use App\Application\AI\DTO\LlmResponse;
use App\Application\Exceptions\ApplicationException;
use App\Application\Observability\Contracts\MetricsCollectorInterface;
use App\Application\Observability\Contracts\StructuredLoggerInterface;
use Throwable;

final readonly class GenerateResponseHandler
{
    public function __construct(
        private LlmGatewayInterface $gateway,
        private ?StructuredLoggerInterface $logger = null,
        private ?MetricsCollectorInterface $metrics = null,
    ) {}

    public function handle(GenerateResponseCommand $command): LlmResponse
    {
        $started = hrtime(true);
        $this->logger?->info('llm.call.started', [
            'conversation_id' => $command->request->conversation_id,
        ]);

        try {
            $response = $this->gateway->generate($command->request);
            $latencyMs = (hrtime(true) - $started) / 1_000_000;
            $this->metrics?->timing('llm.latency_ms', $latencyMs, [
                'model' => $response->model,
            ]);
            $this->metrics?->gauge('llm.tokens_used', (float) $response->tokens_used, [
                'model' => $response->model,
            ]);
            $this->logger?->info('llm.call.completed', [
                'model' => $response->model,
                'tokens_used' => $response->tokens_used,
                'latency_ms' => $response->latency_ms > 0 ? $response->latency_ms : round($latencyMs, 2),
            ]);

            return $response;
        } catch (Throwable $exception) {
            $latencyMs = (hrtime(true) - $started) / 1_000_000;
            $this->metrics?->increment('llm.failures');
            $this->metrics?->timing('llm.latency_ms', $latencyMs, ['status' => 'failed']);
            $this->logger?->error('llm.call.failed', [
                'latency_ms' => round($latencyMs, 2),
                'error_class' => $exception::class,
            ]);
            throw new ApplicationException('LLM response generation failed.', previous: $exception);
        }
    }
}
