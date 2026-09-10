<?php

namespace Tests\Unit\Application;

use App\Application\AI\Contracts\AiProcessingDispatcherInterface;
use App\Application\Commands\ReceiveIncomingMessageBatch\ReceiveIncomingMessageBatchCommand;
use App\Application\Commands\ReceiveIncomingMessageBatch\ReceiveIncomingMessageBatchHandler;
use App\Application\Contracts\AiProcessingTaskRepositoryInterface;
use App\Application\Contracts\MessageBatchRepositoryInterface;
use App\Application\DTO\IncomingBatchMessageItem;
use App\Application\DTO\IncomingMessageBatchData;
use App\Application\DTO\MessageBatchIngestionResult;
use App\Application\Exceptions\ApplicationException;
use App\Domain\AI\Entities\AiProcessingTask;
use App\Domain\AI\ValueObjects\AiProcessingTaskId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\MessageBatch;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MessageBatchIngestionTest extends TestCase
{
    private MemoryUsers $users;

    private MemoryConversations $conversations;

    private MemoryMessages $messages;

    private MemoryMessageBatches $batches;

    private ReceiveIncomingMessageBatchHandler $handler;

    protected function setUp(): void
    {
        $this->users = new MemoryUsers;
        $this->conversations = new MemoryConversations;
        $this->messages = new MemoryMessages;
        $this->batches = new MemoryMessageBatches;
        $this->handler = new ReceiveIncomingMessageBatchHandler(
            $this->users,
            $this->conversations,
            $this->messages,
            $this->batches,
            new RecordedEvents,
            new MemoryAiProcessingTasks,
            new NoopAiDispatcher,
        );
    }

    public function test_one_batch_with_multiple_messages_creates_one_conversation(): void
    {
        $result = $this->ingest([
            ['id' => 'tg-1', 'text' => 'سلام'],
            ['id' => 'tg-2', 'text' => 'خوبی؟'],
            ['id' => 'tg-3', 'text' => 'چه خبر؟'],
            ['id' => 'tg-4', 'text' => 'از کجایی؟'],
        ]);

        self::assertTrue($result->created);
        self::assertFalse($result->duplicate);
        self::assertSame(4, $result->message_count);
        self::assertSame(4, $result->created_count);
        self::assertSame(0, $result->duplicate_count);
        self::assertCount(1, $this->users->items);
        self::assertCount(1, $this->conversations->items);
        self::assertCount(4, $this->messages->items);
        self::assertCount(1, $this->batches->items);

        foreach ($this->messages->items as $message) {
            self::assertSame($result->batch_id, (string) $message->batchId);
            self::assertSame($result->conversation_id, (string) $message->conversationId);
        }
    }

    public function test_same_batch_sent_twice_creates_no_duplicates(): void
    {
        $items = [
            ['id' => 'tg-1', 'text' => 'hello'],
            ['id' => 'tg-2', 'text' => 'again'],
        ];
        $first = $this->ingest($items);
        $second = $this->ingest($items);

        self::assertTrue($first->created);
        self::assertFalse($second->created);
        self::assertTrue($second->duplicate);
        self::assertSame(2, $second->duplicate_count);
        self::assertSame(0, $second->created_count);
        self::assertCount(1, $this->users->items);
        self::assertCount(1, $this->conversations->items);
        self::assertCount(2, $this->messages->items);
        self::assertCount(1, $this->batches->items);
    }

    public function test_same_user_different_influencers_creates_separate_conversations(): void
    {
        $first = $this->ingest([['id' => 'tg-1', 'text' => 'hi']], influencerId: 'influencer-1');
        $second = $this->ingest([['id' => 'tg-2', 'text' => 'hi']], influencerId: 'influencer-2');

        self::assertSame($first->user_id, $second->user_id);
        self::assertNotSame($first->conversation_id, $second->conversation_id);
        self::assertCount(1, $this->users->items);
        self::assertCount(2, $this->conversations->items);
        self::assertCount(2, $this->messages->items);
        self::assertCount(2, $this->batches->items);
    }

    public function test_empty_batch_is_rejected(): void
    {
        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Message batch cannot be empty.');

        $this->handler->handle(new ReceiveIncomingMessageBatchCommand(
            new IncomingMessageBatchData('telegram', '100', 'amir', 'tenant-1', 'influencer-1', []),
        ));
    }

    public function test_mixed_duplicate_and_new_messages_are_handled_correctly(): void
    {
        $this->ingest([
            ['id' => 'tg-1', 'text' => 'first'],
            ['id' => 'tg-2', 'text' => 'second'],
        ]);

        $mixed = $this->ingest([
            ['id' => 'tg-2', 'text' => 'second'],
            ['id' => 'tg-3', 'text' => 'third'],
        ]);

        self::assertTrue($mixed->created);
        self::assertSame(1, $mixed->created_count);
        self::assertSame(1, $mixed->duplicate_count);
        self::assertSame(2, $mixed->message_count);
        self::assertCount(3, $this->messages->items);
        self::assertCount(2, $this->batches->items);
        self::assertCount(1, $this->conversations->items);
    }

    /**
     * @param  list<array{id: string, text: string}>  $items
     */
    private function ingest(array $items, string $influencerId = 'influencer-1'): MessageBatchIngestionResult
    {
        $messages = [];
        foreach ($items as $index => $item) {
            $messages[] = new IncomingBatchMessageItem(
                $item['id'],
                $item['text'],
                new DateTimeImmutable('2026-09-07T21:00:0'.$index.'+00:00'),
            );
        }

        return $this->handler->handle(new ReceiveIncomingMessageBatchCommand(
            new IncomingMessageBatchData('telegram', '100', 'amir', 'tenant-1', $influencerId, $messages),
        ));
    }
}

final class MemoryMessageBatches implements MessageBatchRepositoryInterface
{
    /** @var array<string, MessageBatch> */
    public array $items = [];

    public function find(MessageBatchId $batchId): ?MessageBatch
    {
        return $this->items[(string) $batchId] ?? null;
    }

    public function save(MessageBatch $batch): void
    {
        $this->items[(string) $batch->id()] = $batch;
    }
}

final class MemoryAiProcessingTasks implements AiProcessingTaskRepositoryInterface
{
    /** @var array<string, AiProcessingTask> */
    public array $items = [];

    public function find(AiProcessingTaskId $taskId): ?AiProcessingTask
    {
        return $this->items[(string) $taskId] ?? null;
    }

    public function findByMessageBatch(
        TenantId $tenantId,
        InfluencerId $influencerId,
        MessageBatchId $messageBatchId,
    ): ?AiProcessingTask {
        foreach ($this->items as $task) {
            if (
                (string) $task->tenantId === (string) $tenantId
                && (string) $task->influencerId === (string) $influencerId
                && (string) $task->messageBatchId === (string) $messageBatchId
            ) {
                return $task;
            }
        }

        return null;
    }

    public function save(AiProcessingTask $task): void
    {
        $this->items[(string) $task->id()] = $task;
    }
}

final class NoopAiDispatcher implements AiProcessingDispatcherInterface
{
    public function dispatch(AiProcessingTask $task): void {}
}
