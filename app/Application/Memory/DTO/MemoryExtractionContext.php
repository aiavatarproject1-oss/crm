<?php

namespace App\Application\Memory\DTO;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\Entities\MessageBatch;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;

/**
 * Scoped conversation-turn payload for memory extraction (not chat generation).
 */
final readonly class MemoryExtractionContext
{
    /**
     * @param  list<array{message_id: string, content: string, sender: string}>  $messages
     */
    public function __construct(
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public UserId $userId,
        public MessageBatchId $batchId,
        public string $platform,
        public array $messages,
        public array $metadata = [],
    ) {}

    /**
     * @param  list<Message>  $messages
     */
    public static function fromBatch(MessageBatch $batch, array $messages): self
    {
        $batchMessageIds = array_map(static fn ($id): string => (string) $id, $batch->messageIds);
        $items = [];
        foreach ($messages as $message) {
            if (! in_array((string) $message->id(), $batchMessageIds, true)) {
                continue;
            }
            $items[] = [
                'message_id' => (string) $message->id(),
                'content' => $message->content->value,
                'sender' => $message->sender,
            ];
        }

        return new self(
            $batch->tenantId,
            $batch->influencerId,
            $batch->userId,
            $batch->id(),
            $batch->platform->value,
            $items,
            [
                'conversation_id' => (string) $batch->conversationId,
                'message_count' => $batch->messageCount,
                ...$batch->metadata,
            ],
        );
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => (string) $this->tenantId,
            'influencer_id' => (string) $this->influencerId,
            'user_id' => (string) $this->userId,
            'batch_id' => (string) $this->batchId,
            'platform' => $this->platform,
            'messages' => $this->messages,
            'metadata' => $this->metadata,
        ];
    }
}
