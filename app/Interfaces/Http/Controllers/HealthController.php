<?php

namespace App\Interfaces\Http\Controllers;

use App\Application\Health\HealthCheckService;
use Illuminate\Http\JsonResponse;

final readonly class HealthController
{
    public function __construct(private HealthCheckService $health) {}

    public function __invoke(): JsonResponse
    {
        $report = $this->health->run();

        return response()->json([
            'success' => $report['healthy'],
            'data' => $report,
        ], $report['healthy'] ? 200 : 503);
    }
}
