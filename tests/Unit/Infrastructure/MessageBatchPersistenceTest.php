<?php

namespace Tests\Unit\Infrastructure;

use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\MessageBatch;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Persistence\MongoDB\Documents\MessageBatchDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\MessageBatchMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MessageBatchPersistenceTest extends TestCase
{
    public function test_message_batch_document_uses_table_not_collection(): void
    {
        $document = new MessageBatchDocument;
        self::assertSame('message_batches', $document->getTable());
    }

    public function test_mapper_round_trips_message_batch(): void
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

        $mapper = new MessageBatchMapper;
        $document = $mapper->toDocument($batch);
        $hydrated = $mapper->toDomain($document);

        self::assertSame((string) $batch->id(), (string) $hydrated->id());
        self::assertSame(2, $hydrated->messageCount);
        self::assertSame(['m-1', 'm-2'], array_map(static fn (MessageId $id): string => (string) $id, $hydrated->messageIds));
        self::assertSame('bot_buffer', $hydrated->metadata['source']);
    }
}
