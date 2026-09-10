<?php

namespace App\Domain\Shared\Events;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\ValueObjects\ExternalMessageId;
use App\Domain\Message\ValueObjects\MessageContent;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;

final readonly class MessageCreated
{
    public function __construct(
        public MessageId $messageId,
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public ConversationId $conversationId,
        public string $sender,
        public MessageContent $content,
        public ExternalMessageId $externalMessageId,
        public MessagePlatform $platform,
        public DateTimeImmutable $occurredAt,
        public string $direction = 'incoming',
        public string $contentType = 'text',
        public array $metadata = [],
    ) {}
}
