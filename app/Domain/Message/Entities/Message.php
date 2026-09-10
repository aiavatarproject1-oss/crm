<?php

namespace App\Domain\Message\Entities;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\ValueObjects\ExternalMessageId;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageContent;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Events\MessageCreated;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;

final class Message implements Entity
{
    private array $domainEvents = [];

    private function __construct(
        private readonly MessageId $messageId,
        public readonly TenantId $tenantId,
        public readonly InfluencerId $influencerId,
        public readonly ConversationId $conversationId,
        public readonly string $sender,
        public readonly MessageContent $content,
        public readonly ExternalMessageId $externalMessageId,
        public readonly MessagePlatform $platform,
        public readonly DateTimeImmutable $createdAt,
        public readonly string $direction,
        public readonly string $contentType,
        public readonly array $metadata,
        public readonly ?MessageBatchId $batchId,
    ) {}

    public static function create(
        MessageId $messageId,
        TenantId $tenantId,
        InfluencerId $influencerId,
        ConversationId $conversationId,
        string $sender,
        MessageContent $content,
        ExternalMessageId $externalMessageId,
        MessagePlatform $platform,
        ?DateTimeImmutable $occurredAt = null,
        string $direction = 'incoming',
        string $contentType = 'text',
        array $metadata = [],
        ?MessageBatchId $batchId = null,
    ): self {
        if (trim($sender) === '') {
            throw new DomainException('Message sender cannot be empty.');
        }

        $occurredAt ??= new DateTimeImmutable;
        if (! in_array($sender, ['user', 'ai', 'admin', 'system'], true)) {
            throw new DomainException('Unsupported message sender type.');
        }
        if (! in_array($direction, ['incoming', 'outgoing'], true)) {
            throw new DomainException('Unsupported message direction.');
        }
        if (! in_array($contentType, ['text', 'image', 'video', 'audio', 'voice', 'other'], true)) {
            throw new DomainException('Unsupported message content type.');
        }

        $message = new self($messageId, $tenantId, $influencerId, $conversationId, $sender, $content, $externalMessageId, $platform, $occurredAt, $direction, $contentType, $metadata, $batchId);
        $message->domainEvents[] = new MessageCreated(
            $messageId,
            $tenantId,
            $influencerId,
            $conversationId,
            $sender,
            $content,
            $externalMessageId,
            $platform,
            $occurredAt,
            $direction,
            $contentType,
            $metadata,
        );

        return $message;
    }

    public function id(): MessageId
    {
        return $this->messageId;
    }

    public function releaseDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
