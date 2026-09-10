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
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Phase 13.1 identity & conversation continuity scenarios.
 */
final class IdentityResolutionTest extends TestCase
{
    private IdentityMemoryUsers $users;

    private IdentityMemoryConversations $conversations;

    private IdentityMemoryMessages $messages;

    private ReceiveIncomingMessageHandler $handler;

    protected function setUp(): void
    {
        $this->users = new IdentityMemoryUsers;
        $this->conversations = new IdentityMemoryConversations;
        $this->messages = new IdentityMemoryMessages;
        $events = new class implements DomainEventPublisherInterface
        {
            public function publish(object $event): void {}
        };
        $this->handler = new ReceiveIncomingMessageHandler(
            new ReceiveIncomingMessageBatchHandler(
                $this->users,
                $this->conversations,
                $this->messages,
                new MemoryMessageBatches,
                $events,
                new MemoryAiProcessingTasks,
                new NoopAiDispatcher,
            ),
        );
    }

    public function test_same_telegram_user_reuses_user_and_conversation(): void
    {
        $first = $this->ingest('111', 'msg-1');
        $usersAfterFirst = count($this->users->items);
        $conversationsAfterFirst = count($this->conversations->items);
        $messagesAfterFirst = count($this->messages->items);

        $second = $this->ingest('111', 'msg-2');

        self::assertSame($first->user_id, $second->user_id);
        self::assertSame($first->conversation_id, $second->conversation_id);
        self::assertSame(0, count($this->users->items) - $usersAfterFirst);
        self::assertSame(0, count($this->conversations->items) - $conversationsAfterFirst);
        self::assertSame(1, count($this->messages->items) - $messagesAfterFirst);
    }

    public function test_two_different_telegram_users_create_separate_aggregates(): void
    {
        $this->ingest('111', 'msg-a');
        $this->ingest('222', 'msg-b');

        self::assertCount(2, $this->users->items);
        self::assertCount(2, $this->conversations->items);
        self::assertCount(2, $this->messages->items);
    }

    public function test_same_user_different_influencer_creates_second_conversation_only(): void
    {
        $first = $this->ingest('111', 'msg-1', influencerId: 'influencer-sofia');
        $second = $this->ingest('111', 'msg-2', influencerId: 'influencer-alex');

        self::assertSame($first->user_id, $second->user_id);
        self::assertNotSame($first->conversation_id, $second->conversation_id);
        self::assertCount(1, $this->users->items);
        self::assertCount(2, $this->conversations->items);
    }

    public function test_duplicate_external_message_id_does_not_create_second_message(): void
    {
        $first = $this->ingest('111', 'same-external-id');
        $second = $this->ingest('111', 'same-external-id');

        self::assertTrue($second->duplicate);
        self::assertFalse($second->created);
        self::assertSame($first->message_id, $second->message_id);
        self::assertCount(1, $this->messages->items);
    }

    public function test_same_platform_user_id_in_different_tenants_is_isolated(): void
    {
        $first = $this->ingest('111', 'msg-1', tenantId: 'tenant-a');
        $second = $this->ingest('111', 'msg-2', tenantId: 'tenant-b');

        self::assertNotSame($first->user_id, $second->user_id);
        self::assertNotSame($first->conversation_id, $second->conversation_id);
        self::assertCount(2, $this->users->items);
        self::assertCount(2, $this->conversations->items);
    }

    private function ingest(
        string $platformUserId,
        string $messageId,
        string $tenantId = 'tenant-demo',
        string $influencerId = 'influencer-sofia',
    ): MessageIngestionResult {
        $data = new IncomingPlatformMessageData(
            'telegram',
            '1.0',
            $messageId,
            $platformUserId,
            'amir',
            'Hello',
            $tenantId,
            $influencerId,
            [],
            [],
            new DateTimeImmutable('2026-09-07T12:00:00+00:00'),
        );

        return $this->handler->handle(new ReceiveIncomingMessageCommand($data));
    }
}

final class IdentityMemoryUsers implements UserRepositoryInterface
{
    /** @var array<string, User> */
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

final class IdentityMemoryConversations implements ConversationRepositoryInterface
{
    /** @var array<string, Conversation> */
    public array $items = [];

    public function find(ConversationId $conversationId): ?Conversation
    {
        return $this->items[(string) $conversationId] ?? null;
    }

    public function findActive(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, string $platform): ?Conversation
    {
        foreach ($this->items as $conversation) {
            if (
                (string) $conversation->tenantId === (string) $tenantId
                && (string) $conversation->influencerId === (string) $influencerId
                && (string) $conversation->userId === (string) $userId
                && $conversation->platform === $platform
                && $conversation->status()->value === 'active'
            ) {
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

final class IdentityMemoryMessages implements MessageRepositoryInterface
{
    /** @var array<string, Message> */
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
            if (
                (string) $message->tenantId === (string) $tenantId
                && (string) $message->influencerId === (string) $influencerId
                && $message->platform->value === $platform
                && (string) $message->externalMessageId === $externalMessageId
            ) {
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
