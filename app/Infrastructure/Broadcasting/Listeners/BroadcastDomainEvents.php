<?php

namespace App\Infrastructure\Broadcasting\Listeners;

use App\Domain\Shared\Events\ConversationUpdated;
use App\Domain\Shared\Events\MessageCreated;
use App\Infrastructure\Broadcasting\Events\ConversationMessageBroadcast;
use App\Infrastructure\Broadcasting\Events\ConversationUpdatedBroadcast;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bridges pure domain events to websocket broadcasts. Failures are logged, never rethrown,
 * so a Reverb outage can never break message persistence or the AI pipeline.
 */
final class BroadcastDomainEvents
{
    public function onMessageCreated(MessageCreated $event): void
    {
        $this->safely(static fn () => ConversationMessageBroadcast::dispatch($event));
    }

    public function onConversationUpdated(ConversationUpdated $event): void
    {
        $this->safely(static fn () => ConversationUpdatedBroadcast::dispatch($event));
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning('broadcast.failed', ['error' => $exception->getMessage()]);
        }
    }
}
