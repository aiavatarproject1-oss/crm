<?php

namespace App\Infrastructure\Health;

use App\Application\Health\Contracts\HealthCheckInterface;
use App\Application\Health\HealthCheckResult;
use MongoDB\Client;
use Throwable;

final class MongoHealthCheck implements HealthCheckInterface
{
    public function name(): string
    {
        return 'mongodb';
    }

    public function check(): HealthCheckResult
    {
        $started = hrtime(true);

        try {
            $uri = (string) config('database.connections.mongodb.dsn');
            $database = (string) config('database.connections.mongodb.database');
            $client = new Client($uri);
            $client->selectDatabase($database)->command(['ping' => 1]);
            $latency = (hrtime(true) - $started) / 1_000_000;

            return new HealthCheckResult($this->name(), true, 'MongoDB ping succeeded.', $latency);
        } catch (Throwable $exception) {
            $latency = (hrtime(true) - $started) / 1_000_000;

            return new HealthCheckResult($this->name(), false, 'MongoDB unavailable.', $latency, [
                'error_class' => $exception::class,
            ]);
        }
    }
}
