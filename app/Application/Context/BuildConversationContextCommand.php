<?php

namespace App\Application\Context;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;

final readonly class BuildConversationContextCommand
{
    public function __construct(public TenantId $tenant_id, public InfluencerId $influencer_id, public UserId $user_id, public ConversationId $conversation_id, public int $message_limit = 20, public int $memory_limit = 10, public string $query_text = '', public int $knowledge_limit = 5) {}
}
