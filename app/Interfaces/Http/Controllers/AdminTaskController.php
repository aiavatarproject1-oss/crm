<?php

namespace App\Interfaces\Http\Controllers;

use App\Application\Contracts\AdminTaskRepositoryInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Quality\Entities\AdminTask;
use App\Domain\Tenant\ValueObjects\TenantId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class AdminTaskController
{
    public function __construct(private AdminTaskRepositoryInterface $tasks) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['nullable', 'string'],
            'influencer_id' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $tasks = $this->tasks->findPending(
            isset($validated['tenant_id']) ? new TenantId((string) $validated['tenant_id']) : null,
            isset($validated['influencer_id']) ? new InfluencerId((string) $validated['influencer_id']) : null,
            (int) ($validated['limit'] ?? 50),
        );

        return response()->json([
            'success' => true,
            'data' => array_map(static function (AdminTask $task): array {
                return [
                    'id' => (string) $task->id(),
                    'tenant_id' => (string) $task->tenantId,
                    'influencer_id' => (string) $task->influencerId,
                    'conversation_id' => (string) $task->conversationId,
                    'message_id' => (string) $task->messageId,
                    'reason' => $task->reason,
                    'status' => $task->status,
                    'metadata' => $task->metadata,
                ];
            }, $tasks),
        ]);
    }
}
