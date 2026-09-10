<?php

namespace App\Application\Health\Contracts;

use App\Application\Health\HealthCheckResult;

interface HealthCheckInterface
{
    public function name(): string;

    public function check(): HealthCheckResult;
}
