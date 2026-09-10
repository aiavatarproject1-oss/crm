<?php

namespace Tests\Unit\Domain;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\ValueObjects\ExternalMessageId;
use App\Domain\Message\ValueObjects\MessageContent;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Shared\Events\MessageCreated;
use App\Domain\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use TypeError;

class MessageAggregateTest extends TestCase
{
    public function test_it_cannot_be_created_without_a_conversation_id(): void
    {
        $this->expectException(TypeError::class);

        Message::create(
            new MessageId('message-1'),
            new TenantId('tenant-1'),
            new InfluencerId('influencer-1'),
            null,
            'user',
            new MessageContent('Hello'),
            new ExternalMessageId('external-1'),
            new MessagePlatform('telegram'),
        );
    }

    public function test_it_is_independent_and_records_a_message_created_event(): void
    {
        $occurredAt = new DateTimeImmutable('2026-09-03T13:00:00+00:00');
        $message = Message::create(
            new MessageId('message-1'),
            new TenantId('tenant-1'),
            new InfluencerId('influencer-1'),
            new ConversationId('conversation-1'),
            'user',
            new MessageContent('Hello'),
            new ExternalMessageId('external-1'),
            new MessagePlatform('telegram'),
            $occurredAt,
        );

        $events = $message->releaseDomainEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(MessageCreated::class, $events[0]);
        $this->assertSame('message-1', $events[0]->messageId->value);
        $this->assertSame('conversation-1', $events[0]->conversationId->value);
        $this->assertSame('tenant-1', $events[0]->tenantId->value);
        $this->assertSame('influencer-1', $events[0]->influencerId->value);
        $this->assertSame('Hello', $events[0]->content->value);
        $this->assertSame('telegram', $events[0]->platform->value);
        $this->assertSame($occurredAt, $events[0]->occurredAt);
    }
}
