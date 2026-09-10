<?php

namespace App\Domain\Quality\Entities;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Quality\ValueObjects\AdminTaskId;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Tenant\ValueObjects\TenantId;

final readonly class AdminTask implements Entity
{
    public function __construct(
        private AdminTaskId $adminTaskId,
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public ConversationId $conversationId,
        public MessageId $messageId,
        public string $reason,
        public string $status = 'pending',
        public array $metadata = [],
    ) {}

    public function id(): AdminTaskId
    {
        return $this->adminTaskId;
    }
}
