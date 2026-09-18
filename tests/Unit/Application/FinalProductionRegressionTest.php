<?php

namespace Tests\Unit\Application;

use App\Application\AI\Commands\GenerateResponseHandler;
use App\Application\AI\Commands\ProcessConversationTurnHandler;
use App\Application\AI\Commands\ProcessMessageAiPipelineHandler;
use App\Application\AI\Contracts\AiProcessingDispatcherInterface;
use App\Application\AI\Contracts\LlmGatewayInterface;
use App\Application\AI\DTO\LlmRequest;
use App\Application\AI\DTO\LlmResponse;
use App\Application\AI\Prompt\ContextPromptBuilder;
use App\Application\Commands\ReceiveIncomingMessage\ReceiveIncomingMessageCommand;
use App\Application\Commands\ReceiveIncomingMessage\ReceiveIncomingMessageHandler;
use App\Application\Commands\ReceiveIncomingMessageBatch\ReceiveIncomingMessageBatchHandler;
use App\Application\Context\BuildConversationContextHandler;
use App\Application\Context\BuildPersonaContextHandler;
use App\Application\Context\DTO\ConversationContext;
use App\Application\Contracts\AdminTaskRepositoryInterface;
use App\Application\Contracts\ConversationRepositoryInterface;
use App\Application\Contracts\DomainEventPublisherInterface;
use App\Application\Contracts\MemoryRepositoryInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Contracts\PersonaRepositoryInterface;
use App\Application\Contracts\RuleRepositoryInterface;
use App\Application\Contracts\UserRepositoryInterface;
use App\Application\DTO\IncomingPlatformMessageData;
use App\Application\DTO\MessageIngestionResult;
use App\Application\Exceptions\ApplicationException;
use App\Application\Quality\Contracts\QualityCheckerInterface;
use App\Application\Quality\DTO\QualityResult;
use App\Application\RAG\Contracts\KnowledgeRetrieverInterface;
use App\Application\RAG\DTO\KnowledgeSearchResult;
use App\Application\RAG\DTO\RetrievalFilter;
use App\Application\Rules\EvaluateMessageRulesHandler;
use App\Domain\AI\Entities\AiProcessingTask;
use App\Domain\Conversation\Entities\Conversation;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\Entities\Persona;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Influencer\ValueObjects\PersonaId;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Memory\ValueObjects\MemoryId;
use App\Domain\Memory\ValueObjects\MemoryStatus;
use App\Domain\Memory\ValueObjects\MemoryType;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Quality\Entities\AdminTask;
use App\Domain\Rule\Entities\Rule;
use App\Domain\Rule\Services\KeywordRuleMatcher;
use App\Domain\Rule\Services\RegexRuleMatcher;
use App\Domain\Rule\Services\RuleMatcherRegistry;
use App\Domain\Rule\ValueObjects\RuleDecision;
use App\Domain\Rule\ValueObjects\RuleId;
use App\Domain\Rule\ValueObjects\RuleType;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Phase 13.6 — final production regression checklist (application-layer E2E).
 */
final class FinalProductionRegressionTest extends TestCase
{
    public function test_step1_end_to_end_message_flow_persists_user_conversation_and_ai_reply(): void
    {
        $harness = $this->harness([]);
        $result = $harness->ingest('111', 'msg-e2e-1', text: 'Hello Sofia');

        self::assertTrue($result->created);
        self::assertCount(1, $harness->users->items);
        self::assertCount(1, $harness->conversations->items);
        $userMessages = array_values(array_filter($harness->messages->items, fn (Message $m): bool => $m->sender === 'user'));
        $aiMessages = array_values(array_filter($harness->messages->items, fn (Message $m): bool => $m->sender === 'ai'));
        self::assertCount(1, $userMessages);
        self::assertCount(1, $aiMessages);
        self::assertSame(1, $harness->gateway->calls);
        self::assertSame(1, $harness->quality->calls);
        self::assertSame('Generated reply', $aiMessages[0]->content->value);
    }

    public function test_step2_idempotent_external_message_id(): void
    {
        $harness = $this->harness([]);
        $first = $harness->ingest('111', 'same-ext-id', text: 'Hello');
        $second = $harness->ingest('111', 'same-ext-id', text: 'Hello');

        self::assertTrue($first->created);
        self::assertTrue($second->duplicate);
        self::assertSame($first->message_id, $second->message_id);
        self::assertCount(1, $harness->users->items);
        self::assertCount(1, $harness->conversations->items);
        self::assertCount(1, array_filter($harness->messages->items, fn (Message $m): bool => $m->sender === 'user'));
        self::assertSame(1, $harness->gateway->calls);
    }

    public function test_step3_same_user_two_influencers_get_separate_conversations(): void
    {
        $harness = $this->harness([]);
        $a = $harness->ingest('111', 'msg-inf-a', influencerId: 'influencer-sofia');
        $b = $harness->ingest('111', 'msg-inf-b', influencerId: 'influencer-alex');

        self::assertSame($a->user_id, $b->user_id);
        self::assertNotSame($a->conversation_id, $b->conversation_id);
        self::assertCount(1, $harness->users->items);
        self::assertCount(2, $harness->conversations->items);
    }

    public function test_step4_allow_ai_executes_llm_and_block_skips_llm(): void
    {
        $allow = $this->harness([]);
        $allow->ingest('111', 'allow-1', text: 'Hello');
        self::assertSame(1, $allow->gateway->calls);

        $block = $this->harness([
            new Rule(new RuleId('block'), new TenantId('tenant-demo'), new InfluencerId('influencer-sofia'), 'spam', new RuleType(RuleType::KEYWORD), ['spam'], 100, new RuleDecision(RuleDecision::BLOCK), true, 1),
        ]);
        $block->ingest('111', 'block-1', text: 'spam offer');
        self::assertSame(0, $block->gateway->calls);
        self::assertCount(0, array_filter($block->messages->items, fn (Message $m): bool => $m->sender === 'ai'));
    }

    public function test_step4_admin_review_skips_llm(): void
    {
        $harness = $this->harness([
            new Rule(new RuleId('review'), new TenantId('tenant-demo'), new InfluencerId('influencer-sofia'), 'identity', new RuleType(RuleType::KEYWORD), ['AI'], 100, new RuleDecision(RuleDecision::ADMIN_REVIEW), true, 1),
        ]);
        $harness->ingest('111', 'review-1', text: 'Are you an AI?');
        self::assertSame(0, $harness->gateway->calls);
        self::assertCount(0, array_filter($harness->messages->items, fn (Message $m): bool => $m->sender === 'ai'));
    }

    public function test_step5_llm_unavailable_returns_safe_application_exception(): void
    {
        $harness = $this->harness([]);
        $harness->gateway->fail = true;

        try {
            $harness->ingest('111', 'llm-fail', text: 'Hello');
            self::fail('Expected ApplicationException');
        } catch (ApplicationException $exception) {
            self::assertSame('LLM response generation failed.', $exception->getMessage());
            self::assertStringNotContainsString('Ollama unavailable', $exception->getMessage());
            self::assertCount(0, array_filter($harness->messages->items, fn (Message $m): bool => $m->sender === 'ai'));
            self::assertCount(1, $harness->aiTasks->items);
            $task = array_values($harness->aiTasks->items)[0];
            self::assertInstanceOf(AiProcessingTask::class, $task);
            self::assertTrue($task->status()->isFailed());
        }
    }

    public function test_step5_quality_failure_creates_admin_task_without_ai_message(): void
    {
        $harness = $this->harness([]);
        $harness->quality->approved = false;
        $harness->ingest('111', 'quality-fail', text: 'Hello');

        self::assertCount(1, $harness->adminTasks->saved);
        self::assertCount(0, array_filter($harness->messages->items, fn (Message $m): bool => $m->sender === 'ai'));
        self::assertSame(1, $harness->gateway->calls);
    }

    public function test_step5_mongo_style_persistence_failure_does_not_leak_connection_details_in_message(): void
    {
        $harness = $this->harness([]);
        $harness->messages->failOnSave = true;

        try {
            $harness->ingest('111', 'mongo-fail', text: 'Hello');
            self::fail('Expected RuntimeException from persistence');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated persistence failure.', $exception->getMessage());
            self::assertStringNotContainsString('mongodb://', $exception->getMessage());
            self::assertStringNotContainsString('password', strtolower($exception->getMessage()));
        }
    }

    public function test_step6_tenant_isolation_for_users_conversations_and_messages(): void
    {
        $harness = $this->harness([]);
        $a = $harness->ingest('111', 'msg-a', tenantId: 'tenant-a');
        $b = $harness->ingest('111', 'msg-b', tenantId: 'tenant-b');

        self::assertNotSame($a->user_id, $b->user_id);
        self::assertNotSame($a->conversation_id, $b->conversation_id);
        self::assertCount(2, $harness->users->items);
        self::assertCount(2, $harness->conversations->items);

        foreach ($harness->users->items as $user) {
            $tenant = (string) $user->tenantId;
            self::assertContains($tenant, ['tenant-a', 'tenant-b']);
        }

        $tenantAMessages = array_filter(
            $harness->messages->items,
            fn (Message $m): bool => (string) $m->tenantId === 'tenant-a',
        );
        $tenantBMessages = array_filter(
            $harness->messages->items,
            fn (Message $m): bool => (string) $m->tenantId === 'tenant-b',
        );
        self::assertNotEmpty($tenantAMessages);
        self::assertNotEmpty($tenantBMessages);
        foreach ($tenantAMessages as $message) {
            self::assertSame('tenant-a', (string) $message->tenantId);
            self::assertNotSame($b->conversation_id, (string) $message->conversationId);
        }
    }

    private function harness(array $rules): RegressionHarness
    {
        return new RegressionHarness($rules);
    }
}

final class RegressionHarness
{
    public RegressionUsers $users;

    public RegressionConversations $conversations;

    public RegressionMessages $messages;

    public MemoryAiProcessingTasks $aiTasks;

    public RegressionGateway $gateway;

    public RegressionQuality $quality;

    public RegressionAdminTasks $adminTasks;

    private ReceiveIncomingMessageHandler $handler;

    /** @param list<Rule> $rules */
    public function __construct(array $rules)
    {
        $this->users = new RegressionUsers;
        $this->conversations = new RegressionConversations;
        $this->messages = new RegressionMessages;
        $this->aiTasks = new MemoryAiProcessingTasks;
        $this->gateway = new RegressionGateway;
        $this->quality = new RegressionQuality;
        $this->adminTasks = new RegressionAdminTasks;
        $events = new class implements DomainEventPublisherInterface
        {
            public function publish(object $event): void {}
        };
        $batches = new MemoryMessageBatches;
        $pipeline = new ProcessMessageAiPipelineHandler(
            new EvaluateMessageRulesHandler(new RegressionRules($rules), new RuleMatcherRegistry([new KeywordRuleMatcher, new RegexRuleMatcher]), $events),
            new BuildConversationContextHandler($this->messages, new RegressionMemories, new BuildPersonaContextHandler(new RegressionPersonas), new RegressionKnowledge),
            new ContextPromptBuilder,
            new GenerateResponseHandler($this->gateway),
            $this->quality,
            new FakeQualityChecks,
            $this->messages,
            $this->adminTasks,
            $events,
        );
        $turnHandler = new ProcessConversationTurnHandler($this->aiTasks, $batches, $this->messages, $pipeline);
        $dispatcher = new class($turnHandler) implements AiProcessingDispatcherInterface
        {
            public function __construct(private ProcessConversationTurnHandler $handler) {}

            public function dispatch(AiProcessingTask $task): void
            {
                $this->handler->handle($task->id(), 1, 1);
            }
        };
        $this->handler = new ReceiveIncomingMessageHandler(
            new ReceiveIncomingMessageBatchHandler(
                $this->users,
                $this->conversations,
                $this->messages,
                $batches,
                $events,
                $this->aiTasks,
                $dispatcher,
            ),
        );
    }

    public function ingest(
        string $platformUserId,
        string $messageId,
        string $tenantId = 'tenant-demo',
        string $influencerId = 'influencer-sofia',
        string $text = 'Hello',
    ): MessageIngestionResult {
        $data = new IncomingPlatformMessageData(
            'telegram',
            '1.0',
            $messageId,
            $platformUserId,
            'amir',
            $text,
            $tenantId,
            $influencerId,
            [],
            [],
            new DateTimeImmutable('2026-09-07T20:00:00+00:00'),
        );

        return $this->handler->handle(new ReceiveIncomingMessageCommand($data));
    }
}

final class RegressionUsers implements UserRepositoryInterface
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

final class RegressionConversations implements ConversationRepositoryInterface
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

final class RegressionMessages implements MessageRepositoryInterface
{
    /** @var array<string, Message> */
    public array $items = [];

    public bool $failOnSave = false;

    public function find(MessageId $messageId): ?Message
    {
        return $this->items[(string) $messageId] ?? null;
    }

    public function findRecentByConversation(TenantId $tenantId, InfluencerId $influencerId, ConversationId $conversationId, int $limit): array
    {
        $matched = array_values(array_filter(
            $this->items,
            fn (Message $message): bool => (string) $message->tenantId === (string) $tenantId
                && (string) $message->influencerId === (string) $influencerId
                && (string) $message->conversationId === (string) $conversationId,
        ));

        return array_slice($matched, -$limit);
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
        if ($this->failOnSave) {
            throw new RuntimeException('Simulated persistence failure.');
        }
        $this->items[(string) $message->id()] = $message;
    }
}

final class RegressionGateway implements LlmGatewayInterface
{
    public int $calls = 0;

    public bool $fail = false;

    public function generate(LlmRequest $request): LlmResponse
    {
        $this->calls++;
        if ($this->fail) {
            throw new RuntimeException('Ollama unavailable.');
        }

        return new LlmResponse('Generated reply', 'fake-model', 12, 5);
    }
}

final class RegressionQuality implements QualityCheckerInterface
{
    public int $calls = 0;

    public bool $approved = true;

    public function evaluate(string $userMessage, string $aiResponse, ConversationContext $context): QualityResult
    {
        $this->calls++;

        return new QualityResult($this->approved, $this->approved ? 0.95 : 0.2, $this->approved ? [] : ['unsafe'], $this->approved ? 'Approved.' : 'Quality policy failed.');
    }
}

final class RegressionAdminTasks implements AdminTaskRepositoryInterface
{
    /** @var list<AdminTask> */
    public array $saved = [];

    public function save(AdminTask $task): void
    {
        $this->saved[] = $task;
    }

    public function findPending(
        ?TenantId $tenantId = null,
        ?InfluencerId $influencerId = null,
        int $limit = 50,
    ): array {
        return array_slice(array_values(array_filter(
            $this->saved,
            static function (AdminTask $task) use ($tenantId, $influencerId): bool {
                if ($task->status !== 'pending') {
                    return false;
                }
                if ($tenantId !== null && (string) $task->tenantId !== (string) $tenantId) {
                    return false;
                }
                if ($influencerId !== null && (string) $task->influencerId !== (string) $influencerId) {
                    return false;
                }

                return true;
            },
        )), 0, $limit);
    }
}

final readonly class RegressionRules implements RuleRepositoryInterface
{
    public function __construct(private array $rules) {}

    public function findEnabledRules(TenantId $tenantId, InfluencerId $influencerId): array
    {
        return array_values(array_filter(
            $this->rules,
            fn (Rule $rule): bool => (string) $rule->tenantId === (string) $tenantId
                && (string) $rule->influencerId === (string) $influencerId
                && $rule->enabled,
        ));
    }

    public function save(Rule $rule): void {}
}

final class RegressionMemories implements MemoryRepositoryInterface
{
    public function save(Memory $memory): void {}

    public function findById(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, MemoryId $memoryId): ?Memory
    {
        return null;
    }

    public function findActiveForUser(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, int $limit = 50): array
    {
        return $this->findImportantUserMemories($tenantId, $influencerId, $userId, $limit);
    }

    public function findByType(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, MemoryType $type): array
    {
        return [];
    }

    public function searchByScope(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, ?MemoryStatus $status = null, ?MemoryType $type = null, int $limit = 100): array
    {
        return [];
    }

    public function findImportantUserMemories(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, int $limit): array
    {
        return [Memory::create(new MemoryId('memory-1'), $tenantId, $influencerId, $userId, new MemoryType(MemoryType::PREFERENCE), 'Likes tea', 1.0, 0.9)];
    }
}

final class RegressionPersonas implements PersonaRepositoryInterface
{
    public function findByInfluencer(TenantId $tenantId, InfluencerId $influencerId): ?Persona
    {
        return new Persona(new PersonaId('persona-1'), $tenantId, $influencerId, 'Sofia', 'en', 'warm', 'friendly', 'An AI influencer.', ['Be respectful']);
    }

    public function save(Persona $persona): void {}
}

final class RegressionKnowledge implements KnowledgeRetrieverInterface
{
    public function retrieve(
        TenantId $tenantId,
        InfluencerId $influencerId,
        string $query,
        int $limit,
        ?RetrievalFilter $filter = null,
    ): array {
        return [new KnowledgeSearchResult('chunk-1', 'FAQ answer', 0.9, [
            'document_id' => 'doc-1',
        ])];
    }
}
