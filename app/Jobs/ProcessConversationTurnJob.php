<?php

namespace App\Jobs;

use App\Application\AI\Commands\ProcessConversationTurnHandler;
use App\Application\Observability\Contracts\MetricsCollectorInterface;
use App\Application\Observability\Contracts\StructuredLoggerInterface;
use App\Application\Observability\CorrelationContext;
use App\Domain\AI\ValueObjects\AiProcessingTaskId;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessConversationTurnJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $taskId,
        public ?string $correlationId = null,
    ) {}

    public function handle(
        ProcessConversationTurnHandler $handler,
        CorrelationContext $correlation,
        StructuredLoggerInterface $logger,
        MetricsCollectorInterface $metrics,
    ): void {
        if ($this->correlationId !== null && trim($this->correlationId) !== '') {
            $correlation->set($this->correlationId);
            Log::withContext(['correlation_id' => $this->correlationId]);
        }

        $logger->info('queue.job.started', [
            'job' => self::class,
            'task_id' => $this->taskId,
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries,
        ]);

        try {
            $handler->handle(
                new AiProcessingTaskId($this->taskId),
                $this->attempts(),
                $this->tries,
            );
            $logger->info('queue.job.completed', [
                'job' => self::class,
                'task_id' => $this->taskId,
                'attempt' => $this->attempts(),
            ]);
        } catch (Throwable $exception) {
            $metrics->increment('queue.job.failures', 1, [
                'job' => 'ProcessConversationTurnJob',
            ]);
            $logger->error('queue.job.failed', [
                'job' => self::class,
                'task_id' => $this->taskId,
                'attempt' => $this->attempts(),
                'error_class' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
