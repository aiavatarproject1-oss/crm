<?php

namespace App\Infrastructure\Health;

use App\Application\Health\Contracts\HealthCheckInterface;
use App\Application\Health\HealthCheckResult;
use Illuminate\Http\Client\Factory;
use Throwable;

final readonly class OllamaHealthCheck implements HealthCheckInterface
{
    public function __construct(private Factory $http) {}

    public function name(): string
    {
        return 'ollama';
    }

    public function check(): HealthCheckResult
    {
        $started = hrtime(true);
        $baseUrl = rtrim((string) config('services.ollama.base_url'), '/');

        try {
            $response = $this->http
                ->timeout(2)
                ->acceptJson()
                ->get($baseUrl.'/api/tags');
            $latency = (hrtime(true) - $started) / 1_000_000;

            if (! $response->successful()) {
                return new HealthCheckResult($this->name(), false, 'Ollama HTTP check failed.', $latency, [
                    'status' => $response->status(),
                ]);
            }

            return new HealthCheckResult($this->name(), true, 'Ollama reachable.', $latency);
        } catch (Throwable $exception) {
            $latency = (hrtime(true) - $started) / 1_000_000;

            return new HealthCheckResult($this->name(), false, 'Ollama unavailable.', $latency, [
                'error_class' => $exception::class,
            ]);
        }
    }
}
