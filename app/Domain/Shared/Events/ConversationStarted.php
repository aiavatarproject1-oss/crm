<?php

namespace App\Domain\Shared\Events;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;

final readonly class ConversationStarted
{
    public function __construct(
        public ConversationId $conversationId,
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public UserId $userId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
