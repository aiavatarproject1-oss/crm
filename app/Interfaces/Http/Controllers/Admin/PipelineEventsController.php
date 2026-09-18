<?php

namespace App\Interfaces\Http\Controllers\Admin;

use App\Application\Admin\Contracts\PipelineEventReadModelInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class PipelineEventsController
{
    public function __construct(private PipelineEventReadModelInterface $events) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'level' => ['nullable', Rule::in(['info', 'warning', 'error'])],
            'event' => ['nullable', 'string', 'max:100'],
            'conversation_id' => ['nullable', 'string'],
            'correlation_id' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);
        $result = $this->events->paginate($page, $perPage, $validated);

        return response()->json([
            'success' => true,
            'data' => $result['items'],
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']],
        ]);
    }

    public function events(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->events->eventNames()]);
    }
}
