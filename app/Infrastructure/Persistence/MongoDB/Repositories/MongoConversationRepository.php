<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\ConversationRepositoryInterface;
use App\Domain\Conversation\Entities\Conversation;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Conversation\ValueObjects\ConversationStatus;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Persistence\MongoDB\Documents\ConversationDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\ConversationMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;

final class MongoConversationRepository implements ConversationRepositoryInterface
{
    public function __construct(private readonly ConversationMapper $mapper) {}

    public function find(ConversationId $conversationId): ?Conversation
    {
        $document = ConversationDocument::query()->find((string) $conversationId);

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findActive(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, string $platform): ?Conversation
    {
        $document = ConversationDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('user_id', (string) $userId)
            ->where('platform', $platform)
            ->where('status', ConversationStatus::ACTIVE)
            ->orderByDesc('last_activity_at')
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function save(Conversation $conversation): void
    {
        ExplicitIdPersister::save($this->mapper->toDocument($conversation), (string) $conversation->id());
    }
}
