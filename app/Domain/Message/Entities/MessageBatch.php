<?php

namespace App\Domain\Message\Entities;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;

/**
 * One user conversation turn: multiple independent Message aggregates buffered by the bot layer.
 */
final class MessageBatch implements Entity
{
    /**
     * @param  list<MessageId>  $messageIds
     */
    private function __construct(
        private readonly MessageBatchId $messageBatchId,
        public readonly TenantId $tenantId,
        public readonly InfluencerId $influencerId,
        public readonly UserId $userId,
        public readonly ConversationId $conversationId,
        public readonly MessagePlatform $platform,
        public readonly int $messageCount,
        public readonly array $messageIds,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly array $metadata,
    ) {}

    /**
     * @param  list<MessageId>  $messageIds
     */
    public static function create(
        MessageBatchId $messageBatchId,
        TenantId $tenantId,
        InfluencerId $influencerId,
        UserId $userId,
        ConversationId $conversationId,
        MessagePlatform $platform,
        array $messageIds,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        array $metadata = [],
    ): self {
        if ($messageIds === []) {
            throw new DomainException('Message batch cannot be empty.');
        }

        foreach ($messageIds as $messageId) {
            if (! $messageId instanceof MessageId) {
                throw new DomainException('Message batch may only contain MessageId values.');
            }
        }

        $createdAt ??= new DateTimeImmutable;
        $updatedAt ??= $createdAt;

        return new self(
            $messageBatchId,
            $tenantId,
            $influencerId,
            $userId,
            $conversationId,
            $platform,
            count($messageIds),
            array_values($messageIds),
            $createdAt,
            $updatedAt,
            $metadata,
        );
    }

    public function id(): MessageBatchId
    {
        return $this->messageBatchId;
    }
}
