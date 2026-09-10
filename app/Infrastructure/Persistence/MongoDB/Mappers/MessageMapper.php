<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\ValueObjects\ExternalMessageId;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageContent;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\MessageDocument;
use DateTimeImmutable;

final class MessageMapper
{
    public function toDocument(Message $message): MessageDocument
    {
        $document = new MessageDocument([
            'tenant_id' => (string) $message->tenantId,
            'influencer_id' => (string) $message->influencerId,
            'conversation_id' => (string) $message->conversationId,
            'batch_id' => $message->batchId === null ? null : (string) $message->batchId,
            'sender_type' => $message->sender,
            'direction' => $message->direction,
            'content_type' => $message->contentType,
            'content' => $message->content->value,
            'external_message_id' => (string) $message->externalMessageId,
            'platform' => $message->platform->value,
            'created_at' => $message->createdAt,
            'metadata' => $message->metadata,
        ]);
        $document->setAttribute('_id', (string) $message->id());

        return $document;
    }

    public function toDomain(MessageDocument $document): Message
    {
        $batchId = $document->batch_id ?? null;
        $message = Message::create(
            new MessageId((string) $document->getAttribute('_id')),
            new TenantId((string) $document->tenant_id),
            new InfluencerId((string) $document->influencer_id),
            new ConversationId((string) $document->conversation_id),
            (string) ($document->sender_type ?? $document->sender),
            new MessageContent((string) $document->content),
            new ExternalMessageId((string) $document->external_message_id),
            new MessagePlatform((string) $document->platform),
            new DateTimeImmutable((string) $document->created_at),
            (string) ($document->direction ?? 'incoming'),
            (string) ($document->content_type ?? 'text'),
            (array) ($document->metadata ?? []),
            $batchId === null || $batchId === '' ? null : new MessageBatchId((string) $batchId),
        );
        $message->releaseDomainEvents();

        return $message;
    }
}
