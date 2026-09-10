<?php

namespace App\Infrastructure\Health;

use App\Application\Health\Contracts\HealthCheckInterface;
use App\Application\Health\HealthCheckResult;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class RedisHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'redis';
    }

    public function check(): HealthCheckResult
    {
        $started = hrtime(true);

        try {
            $connection = (string) config('queue.connections.redis.connection', 'queue');
            $pong = Redis::connection($connection)->ping();
            $latency = (hrtime(true) - $started) / 1_000_000;
            $payload = is_object($pong) && method_exists($pong, 'getPayload')
                ? (string) $pong->getPayload()
                : (is_string($pong) ? $pong : null);
            $ok = $pong === true
                || $pong === 'PONG'
                || $pong === '+PONG'
                || strtoupper((string) $payload) === 'PONG'
                || (is_object($pong) && strtoupper((string) $pong) === 'PONG');

            return new HealthCheckResult(
                $this->name(),
                $ok,
                $ok ? 'Redis ping succeeded.' : 'Redis ping returned unexpected response.',
                $latency,
                $ok ? [] : ['response_type' => get_debug_type($pong)],
            );
        } catch (Throwable $exception) {
            $latency = (hrtime(true) - $started) / 1_000_000;

            return new HealthCheckResult($this->name(), false, 'Redis unavailable.', $latency, [
                'error_class' => $exception::class,
                'error' => $exception->getMessage(),
                'connection' => (string) config('queue.connections.redis.connection', 'queue'),
            ]);
        }
    }
}
