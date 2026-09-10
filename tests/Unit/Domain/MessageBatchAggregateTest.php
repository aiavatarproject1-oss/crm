<?php

namespace Tests\Unit\Domain;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\MessageBatch;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MessageBatchAggregateTest extends TestCase
{
    public function test_it_creates_a_conversation_turn_batch(): void
    {
        $createdAt = new DateTimeImmutable('2026-09-07T21:00:00+00:00');
        $batch = MessageBatch::create(
            new MessageBatchId('batch-1'),
            new TenantId('tenant-1'),
            new InfluencerId('influencer-1'),
            new UserId('user-1'),
            new ConversationId('conversation-1'),
            new MessagePlatform('telegram'),
            [new MessageId('m-1'), new MessageId('m-2')],
            $createdAt,
            $createdAt,
            ['source' => 'bot_buffer'],
        );

        self::assertSame('batch-1', (string) $batch->id());
        self::assertSame(2, $batch->messageCount);
        self::assertCount(2, $batch->messageIds);
        self::assertSame('telegram', $batch->platform->value);
    }

    public function test_empty_batch_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Message batch cannot be empty.');

        MessageBatch::create(
            new MessageBatchId('batch-1'),
            new TenantId('tenant-1'),
            new InfluencerId('influencer-1'),
            new UserId('user-1'),
            new ConversationId('conversation-1'),
            new MessagePlatform('telegram'),
            [],
        );
    }
}
