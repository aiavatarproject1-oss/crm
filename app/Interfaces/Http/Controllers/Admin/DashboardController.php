<?php

namespace App\Interfaces\Http\Controllers\Admin;

use App\Application\Admin\Contracts\ConversationReadModelInterface;
use App\Application\Health\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Throwable;

final readonly class DashboardController
{
    public function __construct(
        private ConversationReadModelInterface $conversations,
        private HealthCheckService $health,
    ) {}

    public function stats(): JsonResponse
    {
        try {
            $health = $this->health->run();
        } catch (Throwable $exception) {
            $health = ['status' => 'unknown', 'error' => $exception->getMessage()];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $this->conversations->dashboardStats(),
                'health' => $health,
                'models' => [
                    'chat' => config('services.ollama.model'),
                    'vision' => config('services.ollama.vision_model'),
                    'quality' => config('services.ollama.quality_model'),
                    'embedding' => config('services.ollama.embedding_model'),
                ],
                'server_time' => now()->toAtomString(),
            ],
        ]);
    }
}
