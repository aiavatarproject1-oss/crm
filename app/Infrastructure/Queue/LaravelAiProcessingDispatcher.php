<?php

namespace App\Infrastructure\Queue;

use App\Application\AI\Contracts\AiProcessingDispatcherInterface;
use App\Application\Observability\CorrelationContext;
use App\Domain\AI\Entities\AiProcessingTask;
use App\Jobs\ProcessConversationTurnJob;

final readonly class LaravelAiProcessingDispatcher implements AiProcessingDispatcherInterface
{
    public function __construct(private CorrelationContext $correlation) {}

    public function dispatch(AiProcessingTask $task): void
    {
        ProcessConversationTurnJob::dispatch((string) $task->id(), $this->correlation->get());
    }
}
