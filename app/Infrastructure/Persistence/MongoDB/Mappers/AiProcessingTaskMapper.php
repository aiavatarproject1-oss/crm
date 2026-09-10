<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\AI\Entities\AiProcessingTask;
use App\Domain\AI\ValueObjects\AiProcessingTaskId;
use App\Domain\AI\ValueObjects\AiProcessingTaskStatus;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\AiProcessingTaskDocument;
use DateTimeImmutable;

final class AiProcessingTaskMapper
{
    public function toDocument(AiProcessingTask $task): AiProcessingTaskDocument
    {
        $document = new AiProcessingTaskDocument([
            'tenant_id' => (string) $task->tenantId,
            'influencer_id' => (string) $task->influencerId,
            'conversation_id' => (string) $task->conversationId,
            'message_batch_id' => (string) $task->messageBatchId,
            'status' => $task->status()->value,
            'attempts' => $task->attempts(),
            'error' => $task->error(),
            'started_at' => $task->startedAt(),
            'finished_at' => $task->finishedAt(),
            'metadata' => $task->metadata,
        ]);
        $document->setAttribute('_id', (string) $task->id());

        return $document;
    }

    public function toDomain(AiProcessingTaskDocument $document): AiProcessingTask
    {
        $startedAt = $document->started_at;
        $finishedAt = $document->finished_at;

        return AiProcessingTask::reconstitute(
            new AiProcessingTaskId((string) $document->getAttribute('_id')),
            new TenantId((string) $document->tenant_id),
            new InfluencerId((string) $document->influencer_id),
            new ConversationId((string) $document->conversation_id),
            new MessageBatchId((string) $document->message_batch_id),
            new AiProcessingTaskStatus((string) $document->status),
            (int) $document->attempts,
            $document->error !== null ? (string) $document->error : null,
            $startedAt instanceof DateTimeImmutable ? $startedAt : ($startedAt === null ? null : new DateTimeImmutable((string) $startedAt)),
            $finishedAt instanceof DateTimeImmutable ? $finishedAt : ($finishedAt === null ? null : new DateTimeImmutable((string) $finishedAt)),
            (array) ($document->metadata ?? []),
        );
    }
}
