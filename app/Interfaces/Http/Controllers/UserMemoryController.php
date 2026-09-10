<?php

namespace App\Interfaces\Http\Controllers;

use App\Application\Contracts\MemoryRepositoryInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class UserMemoryController
{
    public function __construct(private MemoryRepositoryInterface $memories) {}

    public function index(Request $request, string $userId): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string'],
            'influencer_id' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $memories = $this->memories->findActiveForUser(
            new TenantId((string) $validated['tenant_id']),
            new InfluencerId((string) $validated['influencer_id']),
            new UserId($userId),
            (int) ($validated['limit'] ?? 50),
        );

        return response()->json([
            'success' => true,
            'data' => array_map(static function (Memory $memory): array {
                return [
                    'id' => (string) $memory->id(),
                    'type' => $memory->type->value,
                    'content' => $memory->content,
                    'confidence_score' => $memory->confidenceScore,
                    'importance_score' => $memory->importanceScore,
                    'status' => $memory->status()->value,
                    'source_message_batch_id' => $memory->sourceMessageBatchId === null
                        ? null
                        : (string) $memory->sourceMessageBatchId,
                    'created_at' => $memory->createdAt->format(DATE_ATOM),
                ];
            }, $memories),
        ]);
    }
}
