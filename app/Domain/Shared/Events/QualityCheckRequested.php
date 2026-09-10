<?php

namespace App\Domain\Shared\Events;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;

final readonly class QualityCheckRequested
{
    public function __construct(
        public MessageId $messageId,
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public ConversationId $conversationId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
