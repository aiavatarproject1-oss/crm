<?php

namespace App\Infrastructure\Observability;

use App\Application\Observability\Contracts\PipelineEventSinkInterface;
use App\Infrastructure\Broadcasting\Events\PipelineEventBroadcast;

/**
 * Pushes pipeline events to the admin panel over Reverb (private `admin.pipeline`).
 */
final class BroadcastPipelineEventSink implements PipelineEventSinkInterface
{
    public function accept(string $level, string $event, array $payload): void
    {
        PipelineEventBroadcast::dispatch($level, $event, $payload);
    }
}
