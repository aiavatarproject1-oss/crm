<?php

namespace App\Application\Health;

use App\Application\Health\Contracts\HealthCheckInterface;

final readonly class HealthCheckService
{
    /**
     * @param  list<HealthCheckInterface>  $checks
     */
    public function __construct(private array $checks) {}

    /**
     * @return array{healthy: bool, checks: list<array<string, mixed>>}
     */
    public function run(): array
    {
        $results = [];
        $healthy = true;
        foreach ($this->checks as $check) {
            $result = $check->check();
            $results[] = $result->toArray();
            if (! $result->healthy) {
                $healthy = false;
            }
        }

        return [
            'healthy' => $healthy,
            'checks' => $results,
        ];
    }
}
