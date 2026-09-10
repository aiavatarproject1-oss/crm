<?php

namespace App\Domain\Shared\Events;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Conversation\ValueObjects\ConversationStatus;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;

final readonly class ConversationUpdated
{
    public function __construct(
        public ConversationId $conversationId,
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public ConversationStatus $status,
        public DateTimeImmutable $occurredAt,
    ) {}
}
