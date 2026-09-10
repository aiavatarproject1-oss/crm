<?php

namespace Tests\Unit\Application;

use App\Application\Commands\ReceiveIncomingMessage\ReceiveIncomingMessageCommand;
use App\Application\Commands\ReceiveIncomingMessage\ReceiveIncomingMessageHandler;
use App\Application\Commands\ReceiveIncomingMessageBatch\ReceiveIncomingMessageBatchHandler;
use App\Application\Contracts\ConversationRepositoryInterface;
use App\Application\Contracts\DomainEventPublisherInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Contracts\UserRepositoryInterface;
use App\Application\DTO\IncomingPlatformMessageData;
use App\Application\DTO\MessageIngestionResult;
use App\Domain\Conversation\Entities\Conversation;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Shared\Events\MessageCreated;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MessageIngestionFlowTest extends TestCase
{
    private MemoryUsers $users;

    private MemoryConversations $conversations;

    private MemoryMessages $messages;

    private RecordedEvents $events;

    private ReceiveIncomingMessageHandler $handler;

    protected function setUp(): void
    {
        $this->users = new MemoryUsers;
        $this->conversations = new MemoryConversations;
        $this->messages = new MemoryMessages;
        $this->events = new RecordedEvents;
        $batchHandler = new ReceiveIncomingMessageBatchHandler(
            $this->users,
            $this->conversations,
            $this->messages,
            new MemoryMessageBatches,
            $this->events,
            new MemoryAiProcessingTasks,
            new NoopAiDispatcher,
        );
        $this->handler = new ReceiveIncomingMessageHandler($batchHandler);
    }

    public function test_new_user_conversation_and_message_are_created(): void
    {
        $result = $this->ingest('telegram', 'user-1', 'message-1');
        self::assertTrue($result->created);
        self::assertFalse($result->duplicate);
        self::assertCount(1, $this->users->items);
        self::assertCount(1, $this->conversations->items);
        self::assertCount(1, $this->messages->items);
    }

    public function test_existing_user_and_active_conversation_are_reused(): void
    {
        $first = $this->ingest('telegram', 'user-1', 'message-1');
        $second = $this->ingest('telegram', 'user-1', 'message-2');
        self::assertSame($first->user_id, $second->user_id);
        self::assertSame($first->conversation_id, $second->conversation_id);
        self::assertCount(1, $this->users->items);
        self::assertCount(1, $this->conversations->items);
        self::assertCount(2, $this->messages->items);
    }

    public function test_duplicate_message_returns_existing_information(): void
    {
        $first = $this->ingest('telegram', 'user-1', 'message-1');
        $second = $this->ingest('telegram', 'user-1', 'message-1');
        self::assertSame($first->message_id, $second->message_id);
        self::assertFalse($second->created);
        self::assertTrue($second->duplicate);
        self::assertCount(1, $this->messages->items);
    }

    public function test_platform_identity_is_isolated(): void
    {
        $telegram = $this->ingest('telegram', '100', 'telegram-message');
        $instagram = $this->ingest('instagram', '100', 'instagram-message');
        self::assertNotSame($telegram->user_id, $instagram->user_id);
        self::assertCount(2, $this->users->items);
    }

    public function test_tenant_identity_is_isolated(): void
    {
        $first = $this->ingest('telegram', '100', 'message-1', 'tenant-1');
        $second = $this->ingest('telegram', '100', 'message-1', 'tenant-2');
        self::assertNotSame($first->user_id, $second->user_id);
        self::assertNotSame($first->message_id, $second->message_id);
        self::assertCount(2, $this->users->items);
    }

    public function test_same_user_different_influencer_reuses_user_and_opens_new_conversation(): void
    {
        $first = $this->ingest('telegram', '100', 'message-1', 'tenant-1', 'influencer-1');
        $second = $this->ingest('telegram', '100', 'message-2', 'tenant-1', 'influencer-2');
        self::assertSame($first->user_id, $second->user_id);
        self::assertNotSame($first->conversation_id, $second->conversation_id);
        self::assertCount(1, $this->users->items);
        self::assertCount(2, $this->conversations->items);
        self::assertCount(2, $this->messages->items);
    }

    public function test_message_created_event_is_released_and_published(): void
    {
        $result = $this->ingest('telegram', 'user-1', 'message-1');
        self::assertCount(1, $this->events->items);
        self::assertInstanceOf(MessageCreated::class, $this->events->items[0]);
        self::assertSame($result->message_id, (string) $this->events->items[0]->messageId);
    }

    private function ingest(string $platform, string $userId, string $messageId, string $tenantId = 'tenant-1', string $influencerId = 'influencer-1'): MessageIngestionResult
    {
        $data = new IncomingPlatformMessageData($platform, '1.0', $messageId, $userId, 'amir', 'Hello', $tenantId, $influencerId, [], ['native' => true], new DateTimeImmutable('2026-09-05T10:00:00+00:00'));

        return $this->handler->handle(new ReceiveIncomingMessageCommand($data));
    }
}

final class MemoryUsers implements UserRepositoryInterface
{
    public array $items = [];

    public function find(UserId $userId): ?User
    {
        return $this->items[(string) $userId] ?? null;
    }

    public function findByPlatformIdentity(TenantId $tenantId, string $platform, string $externalUserId): ?User
    {
        foreach ($this->items as $user) {
            if ((string) $user->tenantId === (string) $tenantId && $user->platform === $platform && $user->externalUserId === $externalUserId) {
                return $user;
            }
        }

        return null;
    }

    public function save(User $user): void
    {
        $this->items[(string) $user->id()] = $user;
    }
}

final class MemoryConversations implements ConversationRepositoryInterface
{
    public array $items = [];

    public function find(ConversationId $conversationId): ?Conversation
    {
        return $this->items[(string) $conversationId] ?? null;
    }

    public function findActive(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, string $platform): ?Conversation
    {
        foreach ($this->items as $conversation) {
            if ((string) $conversation->tenantId === (string) $tenantId && (string) $conversation->influencerId === (string) $influencerId && (string) $conversation->userId === (string) $userId && $conversation->platform === $platform && $conversation->status()->value === 'active') {
                return $conversation;
            }
        }

        return null;
    }

    public function save(Conversation $conversation): void
    {
        $this->items[(string) $conversation->id()] = $conversation;
    }
}

final class MemoryMessages implements MessageRepositoryInterface
{
    public array $items = [];

    public function find(MessageId $messageId): ?Message
    {
        return $this->items[(string) $messageId] ?? null;
    }

    public function findRecentByConversation(TenantId $tenantId, InfluencerId $influencerId, ConversationId $conversationId, int $limit): array
    {
        return array_slice(array_values($this->items), -$limit);
    }

    public function findByExternalIdentity(TenantId $tenantId, InfluencerId $influencerId, string $platform, string $externalMessageId): ?Message
    {
        foreach ($this->items as $message) {
            if ((string) $message->tenantId === (string) $tenantId && (string) $message->influencerId === (string) $influencerId && $message->platform->value === $platform && (string) $message->externalMessageId === $externalMessageId) {
                return $message;
            }
        }

        return null;
    }

    public function findByBatchResponse(
        TenantId $tenantId,
        InfluencerId $influencerId,
        MessageBatchId $batchId,
        string $responseType,
    ): ?Message {
        foreach ($this->items as $message) {
            if (
                (string) $message->tenantId === (string) $tenantId
                && (string) $message->influencerId === (string) $influencerId
                && $message->batchId !== null
                && (string) $message->batchId === (string) $batchId
                && $message->sender === 'ai'
                && ($message->metadata['response_type'] ?? null) === $responseType
            ) {
                return $message;
            }
        }

        return null;
    }

    public function save(Message $message): void
    {
        $this->items[(string) $message->id()] = $message;
    }
}

final class RecordedEvents implements DomainEventPublisherInterface
{
    public array $items = [];

    public function publish(object $event): void
    {
        $this->items[] = $event;
    }
}
