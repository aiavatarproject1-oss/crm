<?php

namespace App\Application\AI\Commands;

use App\Application\AI\Contracts\MessageAiPipelineInterface;
use App\Application\Contracts\AiProcessingTaskRepositoryInterface;
use App\Application\Contracts\MessageBatchRepositoryInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Exceptions\ApplicationException;
use App\Application\Observability\Contracts\MetricsCollectorInterface;
use App\Application\Observability\Contracts\StructuredLoggerInterface;
use App\Domain\AI\ValueObjects\AiProcessingTaskId;
use Throwable;

/**
 * Worker-side turn processor: load task, validate ownership, run existing AI pipeline.
 */
final readonly class ProcessConversationTurnHandler
{
    public function __construct(
        private AiProcessingTaskRepositoryInterface $tasks,
        private MessageBatchRepositoryInterface $batches,
        private MessageRepositoryInterface $messages,
        private MessageAiPipelineInterface $pipeline,
        private ?StructuredLoggerInterface $logger = null,
        private ?MetricsCollectorInterface $metrics = null,
    ) {}

    public function handle(AiProcessingTaskId $taskId, int $attempt = 1, int $maxAttempts = 1): void
    {
        $task = $this->tasks->find($taskId);
        if ($task === null) {
            throw new ApplicationException('AI processing task was not found.');
        }

        if ($task->status()->isCompleted()) {
            $this->logger?->info('ai.processing.skipped_completed', [
                'task_id' => (string) $taskId,
                'tenant_id' => (string) $task->tenantId,
                'influencer_id' => (string) $task->influencerId,
            ]);

            return;
        }

        $task->markProcessing();
        $this->tasks->save($task);
        $this->logger?->info('ai.processing.started', [
            'task_id' => (string) $taskId,
            'tenant_id' => (string) $task->tenantId,
            'influencer_id' => (string) $task->influencerId,
            'batch_id' => (string) $task->messageBatchId,
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
        ]);

        $started = hrtime(true);

        try {
            $batch = $this->batches->find($task->messageBatchId);
            if ($batch === null) {
                throw new ApplicationException('Message batch for AI processing task was not found.');
            }

            if ((string) $batch->tenantId !== (string) $task->tenantId
                || (string) $batch->influencerId !== (string) $task->influencerId
                || (string) $batch->conversationId !== (string) $task->conversationId) {
                throw new ApplicationException('AI processing task ownership validation failed.');
            }

            $triggerMessageId = $batch->messageIds[array_key_last($batch->messageIds)];
            $message = $this->messages->find($triggerMessageId);
            if ($message === null) {
                throw new ApplicationException('Trigger message for AI processing task was not found.');
            }

            if ((string) $message->tenantId !== (string) $task->tenantId
                || (string) $message->influencerId !== (string) $task->influencerId
                || (string) $message->conversationId !== (string) $task->conversationId) {
                throw new ApplicationException('AI processing task ownership validation failed.');
            }

            $this->pipeline->process($message, $batch->userId);
            $task->markCompleted();
            $this->tasks->save($task);

            $latencyMs = (hrtime(true) - $started) / 1_000_000;
            $this->metrics?->timing('ai.latency_ms', $latencyMs, [
                'tenant_id' => (string) $task->tenantId,
                'status' => 'completed',
            ]);
            $this->logger?->info('ai.processing.completed', [
                'task_id' => (string) $taskId,
                'tenant_id' => (string) $task->tenantId,
                'influencer_id' => (string) $task->influencerId,
                'batch_id' => (string) $task->messageBatchId,
                'latency_ms' => round($latencyMs, 2),
            ]);
        } catch (Throwable $exception) {
            $safeMessage = $exception instanceof ApplicationException
                ? $exception->getMessage()
                : 'AI processing failed.';

            $latencyMs = (hrtime(true) - $started) / 1_000_000;
            $this->metrics?->timing('ai.latency_ms', $latencyMs, [
                'tenant_id' => (string) $task->tenantId,
                'status' => 'failed',
            ]);

            if ($attempt < $maxAttempts) {
                $task->markRetrying($safeMessage);
                $this->tasks->save($task);
                $this->logger?->warning('ai.processing.retrying', [
                    'task_id' => (string) $taskId,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'error' => $safeMessage,
                ]);
                throw $exception instanceof ApplicationException
                    ? $exception
                    : new ApplicationException($safeMessage, 0, $exception);
            }

            $task->markFailed($safeMessage);
            $this->tasks->save($task);
            $this->metrics?->increment('queue.failures', 1, [
                'tenant_id' => (string) $task->tenantId,
            ]);
            $this->logger?->error('ai.processing.failed', [
                'task_id' => (string) $taskId,
                'tenant_id' => (string) $task->tenantId,
                'influencer_id' => (string) $task->influencerId,
                'error' => $safeMessage,
                'error_class' => $exception::class,
            ]);

            throw $exception instanceof ApplicationException
                ? $exception
                : new ApplicationException($safeMessage, 0, $exception);
        }
    }
}
