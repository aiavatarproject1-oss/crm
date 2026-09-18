<?php

namespace App\Interfaces\Http\Controllers\Admin;

use App\Application\Admin\Contracts\AuditLogRepositoryInterface;
use App\Domain\Admin\Entities\AuditLog;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class AuditLogsController
{
    public function __construct(private AuditLogRepositoryInterface $logs) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'actor_id' => ['nullable', 'string'],
            'action' => ['nullable', 'string', 'max:100'],
            'target_type' => ['nullable', 'string', 'max:50'],
            'target_id' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 50);
        $result = $this->logs->paginate($page, $perPage, $validated);

        return response()->json([
            'success' => true,
            'data' => array_map(static fn (AuditLog $log): array => [
                'id' => $log->id,
                'actor_id' => $log->actorId,
                'actor_username' => $log->actorUsername,
                'action' => $log->action,
                'target_type' => $log->targetType,
                'target_id' => $log->targetId,
                'changes' => $log->changes,
                'context' => $log->context,
                'ip' => $log->ip,
                'user_agent' => $log->userAgent,
                'correlation_id' => $log->correlationId,
                'created_at' => $log->createdAt->format(DateTimeInterface::ATOM),
            ], $result['items']),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']],
        ]);
    }
}
