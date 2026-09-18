<?php

namespace App\Application\Observability\Contracts;

/**
 * Secondary destination for pipeline events (DB store, websocket broadcast, …).
 * Implementations must never throw into the pipeline.
 */
interface PipelineEventSinkInterface
{
    /**
     * @param  array<string, mixed>  $payload  already sanitized (no secrets / binaries)
     */
    public function accept(string $level, string $event, array $payload): void;
}
