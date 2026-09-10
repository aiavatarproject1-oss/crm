<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\MessageRepositoryInterface;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\MessageDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\MessageMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;

class MongoMessageRepository implements MessageRepositoryInterface
{
    public function __construct(private readonly MessageMapper $mapper) {}

    public function find(MessageId $messageId): ?Message
    {
        $document = MessageDocument::query()->find((string) $messageId);

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findByExternalIdentity(TenantId $tenantId, InfluencerId $influencerId, string $platform, string $externalMessageId): ?Message
    {
        $document = MessageDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('platform', $platform)
            ->where('external_message_id', $externalMessageId)
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findByBatchResponse(
        TenantId $tenantId,
        InfluencerId $influencerId,
        MessageBatchId $batchId,
        string $responseType,
    ): ?Message {
        $document = MessageDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('batch_id', (string) $batchId)
            ->where('sender_type', 'ai')
            ->where('metadata.response_type', $responseType)
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findRecentByConversation(TenantId $tenantId, InfluencerId $influencerId, ConversationId $conversationId, int $limit): array
    {
        return MessageDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('conversation_id', (string) $conversationId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (MessageDocument $document) => $this->mapper->toDomain($document))
            ->all();
    }

    public function save(Message $message): void
    {
        ExplicitIdPersister::save(
            $this->mapper->toDocument($message),
            (string) $message->id(),
            fn () => MessageDocument::query()
                ->where('tenant_id', (string) $message->tenantId)
                ->where('influencer_id', (string) $message->influencerId)
                ->where('platform', $message->platform->value)
                ->where('external_message_id', (string) $message->externalMessageId)
                ->first(),
        );
    }

    protected function duplicateIdentity(Message $message): array
    {
        return [
            'tenant_id' => (string) $message->tenantId,
            'influencer_id' => (string) $message->influencerId,
            'platform' => $message->platform->value,
            'external_message_id' => (string) $message->externalMessageId,
        ];
    }
}
