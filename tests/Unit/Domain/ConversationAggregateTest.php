<?php

namespace Tests\Unit\Domain;

use App\Domain\Conversation\Entities\Conversation;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Conversation\ValueObjects\ConversationStatus;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Shared\Events\ConversationStarted;
use App\Domain\Shared\Events\ConversationUpdated;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TypeError;

class ConversationAggregateTest extends TestCase
{
    public function test_it_cannot_start_without_required_identifiers(): void
    {
        $this->expectException(TypeError::class);

        Conversation::start(
            new ConversationId('conversation-1'),
            null,
            new InfluencerId('influencer-1'),
            new UserId('user-1'),
            'telegram',
        );
    }

    public function test_it_manages_lifecycle_activity_and_events_without_messages(): void
    {
        $startedAt = new DateTimeImmutable('2026-09-03T12:00:00+00:00');
        $updatedAt = new DateTimeImmutable('2026-09-03T12:05:00+00:00');
        $conversation = Conversation::start(
            new ConversationId('conversation-1'),
            new TenantId('tenant-1'),
            new InfluencerId('influencer-1'),
            new UserId('user-1'),
            'telegram',
            $startedAt,
        );

        $startedEvents = $conversation->releaseDomainEvents();
        $this->assertCount(1, $startedEvents);
        $this->assertInstanceOf(ConversationStarted::class, $startedEvents[0]);
        $this->assertSame('tenant-1', $startedEvents[0]->tenantId->value);

        $conversation->changeStatus(new ConversationStatus(ConversationStatus::PAUSED), $updatedAt);
        $updatedEvents = $conversation->releaseDomainEvents();

        $this->assertSame(ConversationStatus::PAUSED, $conversation->status()->value);
        $this->assertSame($updatedAt, $conversation->lastActivityAt());
        $this->assertInstanceOf(ConversationUpdated::class, $updatedEvents[0]);
        $this->assertFalse(property_exists($conversation, 'messages'));
    }
}
