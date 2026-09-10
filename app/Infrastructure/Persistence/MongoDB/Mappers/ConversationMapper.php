<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Conversation\Entities\Conversation;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Conversation\ValueObjects\ConversationStatus;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Persistence\MongoDB\Documents\ConversationDocument;
use DateTimeImmutable;

final class ConversationMapper
{
    public function toDocument(Conversation $conversation): ConversationDocument
    {
        $document = new ConversationDocument([
            'tenant_id' => (string) $conversation->tenantId,
            'influencer_id' => (string) $conversation->influencerId,
            'user_id' => (string) $conversation->userId,
            'platform' => $conversation->platform,
            'status' => $conversation->status()->value,
            'started_at' => $conversation->startedAt,
            'last_activity_at' => $conversation->lastActivityAt(),
        ]);
        $document->setAttribute('_id', (string) $conversation->id());

        return $document;
    }

    public function toDomain(ConversationDocument $document): Conversation
    {
        $startedAt = new DateTimeImmutable((string) $document->started_at);
        $lastActivityAt = new DateTimeImmutable((string) $document->last_activity_at);
        $conversation = Conversation::start(
            new ConversationId((string) $document->getAttribute('_id')),
            new TenantId((string) $document->tenant_id),
            new InfluencerId((string) $document->influencer_id),
            new UserId((string) $document->user_id),
            (string) $document->platform,
            $startedAt,
        );

        if ((string) $document->status !== ConversationStatus::ACTIVE) {
            $conversation->changeStatus(new ConversationStatus((string) $document->status), $lastActivityAt);
        } elseif ($lastActivityAt != $startedAt) {
            $conversation->recordActivity($lastActivityAt);
        }

        $conversation->releaseDomainEvents();

        return $conversation;
    }
}
