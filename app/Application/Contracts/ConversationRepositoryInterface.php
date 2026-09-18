<?php

namespace App\Application\Contracts;

use App\Domain\Conversation\Entities\Conversation;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Shared\Contracts\RepositoryInterface;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;

interface ConversationRepositoryInterface extends RepositoryInterface
{
    public function find(ConversationId $conversationId): ?Conversation;

    /**
     * Resolve the open conversation for a user talking to an influencer on a platform.
     *
     * Scope: tenant_id + influencer_id + user_id + platform + status in (active, handoff, paused)
     */
    public function findActive(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, string $platform): ?Conversation;

    public function save(Conversation $conversation): void;
}
