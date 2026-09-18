<?php

namespace App\Infrastructure\Observability;

use App\Application\Observability\Contracts\PipelineEventSinkInterface;
use App\Infrastructure\Persistence\MongoDB\Documents\PipelineEventDocument;
use DateTimeImmutable;

/**
 * Persists pipeline events to `pipeline_events` (TTL-expired) for the admin log viewer.
 */
final class MongoPipelineEventSink implements PipelineEventSinkInterface
{
    public function accept(string $level, string $event, array $payload): void
    {
        $document = new PipelineEventDocument([
            'level' => $level,
            'event' => $event,
            'correlation_id' => $payload['correlation_id'] ?? null,
            'conversation_id' => isset($payload['conversation_id']) ? (string) $payload['conversation_id'] : null,
            'batch_id' => isset($payload['batch_id']) ? (string) $payload['batch_id'] : null,
            'tenant_id' => isset($payload['tenant_id']) ? (string) $payload['tenant_id'] : null,
            'influencer_id' => isset($payload['influencer_id']) ? (string) $payload['influencer_id'] : null,
            'context' => $payload,
            'created_at' => new DateTimeImmutable,
        ]);
        $document->setAttribute('_id', bin2hex(random_bytes(16)));
        $document->save();
    }
}
