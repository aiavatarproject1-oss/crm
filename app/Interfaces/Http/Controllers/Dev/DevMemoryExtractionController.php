<?php

namespace App\Interfaces\Http\Controllers\Dev;

use App\Application\Dev\Services\TriggerMemoryExtractionService;
use Illuminate\Http\JsonResponse;

final readonly class DevMemoryExtractionController
{
    public function __construct(private TriggerMemoryExtractionService $extraction) {}

    public function store(string $messageBatchId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->extraction->handle($messageBatchId),
        ]);
    }
}
