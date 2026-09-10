<?php

namespace Tests\Unit\Application;

use App\Application\Context\BuildConversationContextCommand;
use App\Application\Context\BuildConversationContextHandler;
use App\Application\Context\BuildPersonaContextHandler;
use App\Application\Contracts\MemoryRepositoryInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Contracts\PersonaRepositoryInterface;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\Entities\Persona;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Memory\ValueObjects\MemoryId;
use App\Domain\Memory\ValueObjects\MemoryStatus;
use App\Domain\Memory\ValueObjects\MemoryType;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\ValueObjects\ExternalMessageId;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageContent;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use PHPUnit\Framework\TestCase;

final class ConversationContextTest extends TestCase
{
    public function test_context_contains_recent_messages_and_important_memories(): void
    {
        $handler = new BuildConversationContextHandler(new ContextMessages([$this->message('user', 'Hello'), $this->message('ai', 'Hi')]), new ContextMemories([$this->memory('FACT', 'Lives in Tehran', 0.8)]), new BuildPersonaContextHandler(new EmptyContextPersonas));
        $context = $handler->handle($this->command());

        self::assertSame([['role' => 'user', 'content' => 'Hello'], ['role' => 'assistant', 'content' => 'Hi']], $context->recent_messages);
        self::assertSame('Lives in Tehran', $context->user_memories[0]['content']);
        self::assertTrue($context->influencer_persona->fallback);
    }

    public function test_context_queries_are_tenant_and_influencer_scoped(): void
    {
        $messages = new ContextMessages([]);
        $memories = new ContextMemories([]);
        (new BuildConversationContextHandler($messages, $memories, new BuildPersonaContextHandler(new EmptyContextPersonas)))->handle($this->command());

        self::assertSame('tenant-1', $messages->scope['tenant']);
        self::assertSame('influencer-1', $messages->scope['influencer']);
        self::assertSame('tenant-1', $memories->scope['tenant']);
        self::assertSame('user-1', $memories->scope['user']);
    }

    public function test_memories_are_ordered_by_importance(): void
    {
        $memories = new ContextMemories([$this->memory('FACT', 'Low', 0.2), $this->memory('PREFERENCE', 'High', 0.9)]);
        $context = (new BuildConversationContextHandler(new ContextMessages([]), $memories, new BuildPersonaContextHandler(new EmptyContextPersonas)))->handle($this->command());

        self::assertSame(['High', 'Low'], array_column($context->user_memories, 'content'));
    }

    private function command(): BuildConversationContextCommand
    {
        return new BuildConversationContextCommand(new TenantId('tenant-1'), new InfluencerId('influencer-1'), new UserId('user-1'), new ConversationId('conversation-1'));
    }

    private function message(string $sender, string $content): Message
    {
        return Message::create(new MessageId(bin2hex(random_bytes(4))), new TenantId('tenant-1'), new InfluencerId('influencer-1'), new ConversationId('conversation-1'), $sender, new MessageContent($content), new ExternalMessageId(bin2hex(random_bytes(4))), new MessagePlatform('telegram'));
    }

    private function memory(string $type, string $content, float $importance): Memory
    {
        return Memory::create(new MemoryId(bin2hex(random_bytes(4))), new TenantId('tenant-1'), new InfluencerId('influencer-1'), new UserId('user-1'), new MemoryType($type), $content, 1.0, $importance);
    }
}

final class ContextMessages implements MessageRepositoryInterface
{
    public array $scope = [];

    public function __construct(private array $messages) {}

    public function find(MessageId $messageId): ?Message
    {
        foreach ($this->messages as $message) {
            if ((string) $message->id() === (string) $messageId) {
                return $message;
            }
        }

        return null;
    }

    public function findRecentByConversation(TenantId $tenantId, InfluencerId $influencerId, ConversationId $conversationId, int $limit): array
    {
        $this->scope = ['tenant' => (string) $tenantId, 'influencer' => (string) $influencerId, 'conversation' => (string) $conversationId];

        return array_slice($this->messages, -$limit);
    }

    public function findByExternalIdentity(TenantId $tenantId, InfluencerId $influencerId, string $platform, string $externalMessageId): ?Message
    {
        return null;
    }

    public function findByBatchResponse(
        TenantId $tenantId,
        InfluencerId $influencerId,
        MessageBatchId $batchId,
        string $responseType,
    ): ?Message {
        foreach ($this->messages as $message) {
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

    public function save(Message $message): void {}
}

final class ContextMemories implements MemoryRepositoryInterface
{
    public array $scope = [];

    public function __construct(private array $memories) {}

    public function save(Memory $memory): void
    {
        $this->memories[] = $memory;
    }

    public function findById(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, MemoryId $memoryId): ?Memory
    {
        foreach ($this->memories as $memory) {
            if ((string) $memory->id() === (string) $memoryId && $memory->belongsToScope($tenantId, $influencerId, $userId)) {
                return $memory;
            }
        }

        return null;
    }

    public function findActiveForUser(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, int $limit = 50): array
    {
        return $this->findImportantUserMemories($tenantId, $influencerId, $userId, $limit);
    }

    public function findByType(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, MemoryType $type): array
    {
        return array_values(array_filter(
            $this->findImportantUserMemories($tenantId, $influencerId, $userId, PHP_INT_MAX),
            static fn (Memory $memory): bool => $memory->type->value === $type->value,
        ));
    }

    public function searchByScope(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, ?MemoryStatus $status = null, ?MemoryType $type = null, int $limit = 100): array
    {
        $items = $this->findImportantUserMemories($tenantId, $influencerId, $userId, $limit);
        if ($status !== null) {
            $items = array_values(array_filter($items, static fn (Memory $memory): bool => $memory->status()->value === $status->value));
        }
        if ($type !== null) {
            $items = array_values(array_filter($items, static fn (Memory $memory): bool => $memory->type->value === $type->value));
        }

        return $items;
    }

    public function findImportantUserMemories(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, int $limit): array
    {
        $this->scope = ['tenant' => (string) $tenantId, 'influencer' => (string) $influencerId, 'user' => (string) $userId];
        $items = array_values(array_filter(
            $this->memories,
            static fn (Memory $memory): bool => $memory->belongsToScope($tenantId, $influencerId, $userId) && $memory->status()->isActive(),
        ));
        usort($items, fn (Memory $left, Memory $right): int => $right->importanceScore <=> $left->importanceScore);

        return array_slice($items, 0, $limit);
    }
}

final class EmptyContextPersonas implements PersonaRepositoryInterface
{
    public function findByInfluencer(TenantId $tenantId, InfluencerId $influencerId): ?Persona
    {
        return null;
    }

    public function save(Persona $persona): void {}
}
