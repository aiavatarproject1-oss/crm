<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\MessageBatch;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Persistence\MongoDB\Documents\MessageBatchDocument;
use DateTimeImmutable;

final class MessageBatchMapper
{
    public function toDocument(MessageBatch $batch): MessageBatchDocument
    {
        $document = new MessageBatchDocument([
            'tenant_id' => (string) $batch->tenantId,
            'influencer_id' => (string) $batch->influencerId,
            'user_id' => (string) $batch->userId,
            'conversation_id' => (string) $batch->conversationId,
            'platform' => $batch->platform->value,
            'message_count' => $batch->messageCount,
            'message_ids' => array_map(static fn (MessageId $id): string => (string) $id, $batch->messageIds),
            'created_at' => $batch->createdAt,
            'updated_at' => $batch->updatedAt,
            'metadata' => $batch->metadata,
        ]);
        $document->setAttribute('_id', (string) $batch->id());

        return $document;
    }

    public function toDomain(MessageBatchDocument $document): MessageBatch
    {
        $messageIds = array_map(
            static fn (mixed $id): MessageId => new MessageId((string) $id),
            (array) ($document->message_ids ?? []),
        );

        return MessageBatch::create(
            new MessageBatchId((string) $document->getAttribute('_id')),
            new TenantId((string) $document->tenant_id),
            new InfluencerId((string) $document->influencer_id),
            new UserId((string) $document->user_id),
            new ConversationId((string) $document->conversation_id),
            new MessagePlatform((string) $document->platform),
            $messageIds,
            new DateTimeImmutable((string) $document->created_at),
            new DateTimeImmutable((string) $document->updated_at),
            (array) ($document->metadata ?? []),
        );
    }
}
