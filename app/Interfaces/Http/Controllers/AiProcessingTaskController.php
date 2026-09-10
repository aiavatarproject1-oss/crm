<?php

namespace App\Interfaces\Http\Controllers;

use App\Application\Contracts\AiProcessingTaskRepositoryInterface;
use App\Application\Exceptions\ApplicationException;
use App\Domain\AI\ValueObjects\AiProcessingTaskId;
use Illuminate\Http\JsonResponse;

final readonly class AiProcessingTaskController
{
    public function __construct(private AiProcessingTaskRepositoryInterface $tasks) {}

    public function show(string $taskId): JsonResponse
    {
        $task = $this->tasks->find(new AiProcessingTaskId($taskId));
        if ($task === null) {
            throw new ApplicationException('AI processing task was not found.');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $task->id(),
                'status' => $task->status()->value,
                'attempts' => $task->attempts(),
                'error' => $task->error(),
                'created_at' => $task->startedAt()?->format(DATE_ATOM),
                'started_at' => $task->startedAt()?->format(DATE_ATOM),
                'completed_at' => $task->finishedAt()?->format(DATE_ATOM),
                'finished_at' => $task->finishedAt()?->format(DATE_ATOM),
                'tenant_id' => (string) $task->tenantId,
                'influencer_id' => (string) $task->influencerId,
                'conversation_id' => (string) $task->conversationId,
                'message_batch_id' => (string) $task->messageBatchId,
            ],
        ]);
    }
}
