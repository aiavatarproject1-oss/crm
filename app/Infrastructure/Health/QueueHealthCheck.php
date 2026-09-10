<?php

namespace App\Infrastructure\Health;

use App\Application\Health\Contracts\HealthCheckInterface;
use App\Application\Health\HealthCheckResult;
use Illuminate\Support\Facades\Queue;
use Throwable;

final class QueueHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'queue';
    }

    public function check(): HealthCheckResult
    {
        $started = hrtime(true);
        $driver = (string) config('queue.default', 'sync');

        try {
            if ($driver === 'sync' || $driver === 'null') {
                $latency = (hrtime(true) - $started) / 1_000_000;

                return new HealthCheckResult($this->name(), true, "Queue driver [{$driver}] is local/inline.", $latency, [
                    'driver' => $driver,
                ]);
            }

            // Touch the connection size API to verify the backend responds.
            Queue::connection($driver)->size();
            $latency = (hrtime(true) - $started) / 1_000_000;

            return new HealthCheckResult($this->name(), true, 'Queue connection reachable.', $latency, [
                'driver' => $driver,
            ]);
        } catch (Throwable $exception) {
            $latency = (hrtime(true) - $started) / 1_000_000;

            return new HealthCheckResult($this->name(), false, 'Queue unavailable.', $latency, [
                'driver' => $driver,
                'error_class' => $exception::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
