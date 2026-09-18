<?php

namespace App\Application\AI\Commands;

use App\Application\AI\Contracts\MessageAiPipelineInterface;
use App\Application\AI\Services\ImageMediaCollector;
use App\Application\Contracts\AiProcessingTaskRepositoryInterface;
use App\Application\Contracts\MessageBatchRepositoryInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Exceptions\ApplicationException;
use App\Application\Observability\Contracts\MetricsCollectorInterface;
use App\Application\Observability\Contracts\StructuredLoggerInterface;
use App\Application\Observability\PipelineMonitor;
use App\Domain\AI\ValueObjects\AiProcessingTaskId;
use App\Domain\Message\Entities\Message;
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
        private ?PipelineMonitor $monitor = null,
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

            $batchMessages = $this->loadBatchMessages($batch->messageIds);
            $imageUrls = ImageMediaCollector::fromMessages($batchMessages);
            $batchUserText = $this->batchUserText($batchMessages, $message);
            $expectsImage = $imageUrls !== [] || $this->batchHasImage($batchMessages, $message);

            $this->monitor?->info('ai.turn.started', [
                'task_id' => (string) $taskId,
                'tenant_id' => (string) $task->tenantId,
                'influencer_id' => (string) $task->influencerId,
                'conversation_id' => (string) $task->conversationId,
                'batch_id' => (string) $task->messageBatchId,
                'batch_message_count' => count($batchMessages),
                'image_urls' => $imageUrls,
                'expects_image' => $expectsImage,
                'batch_user_text' => mb_substr($batchUserText, 0, 300),
            ]);

            $result = $this->pipeline->process($message, $batch->userId, [
                'image_urls' => $imageUrls,
                'batch_user_text' => $batchUserText,
                'expects_image' => $expectsImage,
            ]);

            $meta = (array) ($result->response?->metadata ?? []);
            $silent = ($result->response?->model === 'handoff') || (bool) ($meta['silent'] ?? $meta['vision_fail'] ?? false);
            $task->mergeMetadata([
                'vision_used' => ! $silent && (bool) ($meta['vision_used'] ?? false),
                'silent' => $silent,
                'handoff' => $silent,
                'vision_fail' => (bool) ($meta['vision_fail'] ?? false),
                'notify' => array_values(array_filter((array) ($meta['notify'] ?? []))),
                'notify_mode' => (string) ($meta['notify_mode'] ?? 'all'),
                'panel_url' => $meta['panel_url'] ?? null,
                'admin_task_id' => $result->admin_task_id,
                'assistant_message_id' => $result->assistant_message_id,
                'message_batch_id' => (string) $task->messageBatchId,
            ]);

            $task->markCompleted();
            $this->tasks->save($task);

            $this->monitor?->info('ai.turn.completed', [
                'task_id' => (string) $taskId,
                'batch_id' => (string) $task->messageBatchId,
            ]);

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

    /**
     * @param  list<\App\Domain\Message\ValueObjects\MessageId>  $messageIds
     * @return list<Message>
     */
    private function loadBatchMessages(array $messageIds): array
    {
        $messages = [];
        foreach ($messageIds as $messageId) {
            $found = $this->messages->find($messageId);
            if ($found instanceof Message) {
                $messages[] = $found;
            }
        }

        return $messages;
    }

    /**
     * @param  list<Message>  $messages
     */
    private function batchUserText(array $messages, Message $trigger): string
    {
        $parts = [];
        foreach ($messages as $message) {
            if ($message->direction !== 'incoming') {
                continue;
            }
            $text = trim($message->content->value);
            if ($text === '' || $text === '[image]' || $text === '[media]') {
                continue;
            }
            $parts[] = $text;
        }

        if ($parts !== []) {
            return implode(' | ', array_values(array_unique($parts)));
        }

        return $trigger->content->value;
    }

    /**
     * @param  list<Message>  $messages
     */
    private function batchHasImage(array $messages, Message $trigger): bool
    {
        if ($trigger->contentType === 'image') {
            return true;
        }

        foreach ($messages as $message) {
            if ($message->contentType === 'image' || $message->content->value === '[image]') {
                return true;
            }
            if (ImageMediaCollector::fromMetadata($message->metadata) !== []) {
                return true;
            }
        }

        return false;
    }
}
