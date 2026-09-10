<?php

namespace App\Domain\Conversation\Entities;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Conversation\ValueObjects\ConversationStatus;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Events\ConversationStarted;
use App\Domain\Shared\Events\ConversationUpdated;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;

final class Conversation implements Entity
{
    private array $domainEvents = [];

    private function __construct(
        private readonly ConversationId $conversationId,
        public readonly TenantId $tenantId,
        public readonly InfluencerId $influencerId,
        public readonly UserId $userId,
        public readonly string $platform,
        private ConversationStatus $status,
        public readonly DateTimeImmutable $startedAt,
        private DateTimeImmutable $lastActivityAt,
    ) {}

    public static function start(
        ConversationId $conversationId,
        TenantId $tenantId,
        InfluencerId $influencerId,
        UserId $userId,
        string $platform,
        ?DateTimeImmutable $occurredAt = null,
    ): self {
        $occurredAt ??= new DateTimeImmutable;
        $conversation = new self($conversationId, $tenantId, $influencerId, $userId, $platform, ConversationStatus::active(), $occurredAt, $occurredAt);
        $conversation->domainEvents[] = new ConversationStarted($conversationId, $tenantId, $influencerId, $userId, $occurredAt);

        return $conversation;
    }

    public function id(): ConversationId
    {
        return $this->conversationId;
    }

    public function status(): ConversationStatus
    {
        return $this->status;
    }

    public function lastActivityAt(): DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function changeStatus(ConversationStatus $status, ?DateTimeImmutable $occurredAt = null): void
    {
        $this->status = $status;
        $this->recordUpdate($occurredAt ?? new DateTimeImmutable);
    }

    public function recordActivity(?DateTimeImmutable $occurredAt = null): void
    {
        $this->recordUpdate($occurredAt ?? new DateTimeImmutable);
    }

    public function releaseDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    private function recordUpdate(DateTimeImmutable $occurredAt): void
    {
        $this->lastActivityAt = $occurredAt;
        $this->domainEvents[] = new ConversationUpdated($this->conversationId, $this->tenantId, $this->influencerId, $this->status, $occurredAt);
    }
}
