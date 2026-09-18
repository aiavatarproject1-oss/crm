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
use App\Application\Commands\ReceiveIncomingMessageBatch\ReceiveIncomingMessageBatchCommand;
use App\Application\Commands\ReceiveIncomingMessageBatch\ReceiveIncomingMessageBatchHandler;
use App\Application\Context\BuildConversationContextHandler;
use App\Application\Context\BuildPersonaContextHandler;
use App\Application\Context\DTO\ConversationContext;
use App\Application\Contracts\PersonaRepositoryInterface;
use App\Application\DTO\IncomingBatchMessageItem;
use App\Application\DTO\IncomingMessageBatchData;
use App\Application\DTO\MessageBatchIngestionResult;
use App\Application\Exceptions\ApplicationException;
use App\Application\Quality\Contracts\QualityCheckerInterface;
use App\Application\Quality\DTO\QualityResult;
use App\Application\RAG\Contracts\KnowledgeRetrieverInterface;
use App\Application\RAG\DTO\KnowledgeSearchResult;
use App\Application\RAG\DTO\RetrievalFilter;
use App\Application\Rules\EvaluateMessageRulesHandler;
use App\Domain\AI\Entities\AiProcessingTask;
use App\Domain\AI\ValueObjects\AiProcessingTaskStatus;
use App\Domain\AI\ValueObjects\AiResponseType;
use App\Domain\Influencer\Entities\Persona;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Influencer\ValueObjects\PersonaId;
use App\Domain\Message\Entities\Message;
use App\Domain\Rule\Services\KeywordRuleMatcher;
use App\Domain\Rule\Services\RegexRuleMatcher;
use App\Domain\Rule\Services\RuleMatcherRegistry;
use App\Domain\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AsyncAiPipelineTest extends TestCase
{
    public function test_message_creates_task(): void
    {
        $harness = $this->harness();
        $result = $harness->ingest('tenant-1', 'inf-1', 'msg-1', 'Hello');

        self::assertTrue($result->created);
        self::assertCount(1, $harness->aiTasks->items);
        $task = array_values($harness->aiTasks->items)[0];
        self::assertSame($result->batch_id, (string) $task->messageBatchId);
        self::assertSame('tenant-1', (string) $task->tenantId);
        self::assertSame('inf-1', (string) $task->influencerId);
        self::assertTrue($task->status()->isCompleted());
    }

    public function test_job_completes_successfully(): void
    {
        $harness = $this->harness();
        $harness->ingest('tenant-1', 'inf-1', 'msg-1', 'Hello');

        $task = array_values($harness->aiTasks->items)[0];
        self::assertSame(AiProcessingTaskStatus::COMPLETED, $task->status()->value);
        self::assertSame(1, $harness->gateway->calls);
        $aiMessages = array_values(array_filter($harness->messages->items, static fn (Message $m): bool => $m->sender === 'ai'));
        self::assertCount(1, $aiMessages);
        self::assertSame(AiResponseType::CONVERSATION_TURN, $aiMessages[0]->metadata['response_type'] ?? null);
        self::assertSame($task->messageBatchId, $aiMessages[0]->batchId);
    }

    public function test_llm_failure_marks_task_failed(): void
    {
        $harness = $this->harness();
        $harness->gateway->fail = true;

        try {
            $harness->ingest('tenant-1', 'inf-1', 'msg-fail', 'Hello');
            self::fail('Expected ApplicationException');
        } catch (ApplicationException $exception) {
            self::assertSame('LLM response generation failed.', $exception->getMessage());
        }

        $task = array_values($harness->aiTasks->items)[0];
        self::assertTrue($task->status()->isFailed());
        self::assertSame('LLM response generation failed.', $task->error());
        self::assertCount(0, array_filter($harness->messages->items, static fn (Message $m): bool => $m->sender === 'ai'));
    }

    public function test_duplicate_job_does_not_duplicate_response(): void
    {
        $harness = $this->harness();
        $harness->ingest('tenant-1', 'inf-1', 'msg-1', 'Hello');
        $task = array_values($harness->aiTasks->items)[0];
        self::assertSame(1, $harness->gateway->calls);
        self::assertCount(1, array_filter($harness->messages->items, static fn (Message $m): bool => $m->sender === 'ai'));

        $harness->turnHandler->handle($task->id(), 1, 1);

        self::assertSame(1, $harness->gateway->calls);
        self::assertCount(1, array_filter($harness->messages->items, static fn (Message $m): bool => $m->sender === 'ai'));
        self::assertTrue($task->status()->isCompleted());
    }

    public function test_tenant_isolation(): void
    {
        $harness = $this->harness();
        $harness->ingest('tenant-a', 'inf-1', 'msg-a', 'Hello A');
        $harness->ingest('tenant-b', 'inf-1', 'msg-b', 'Hello B');

        self::assertCount(2, $harness->aiTasks->items);
        foreach ($harness->aiTasks->items as $task) {
            $messages = $harness->messages->findByBatchResponse(
                $task->tenantId,
                $task->influencerId,
                $task->messageBatchId,
                AiResponseType::CONVERSATION_TURN,
            );
            self::assertNotNull($messages);
            self::assertSame((string) $task->tenantId, (string) $messages->tenantId);
        }

        $tenantA = array_values(array_filter(
            $harness->aiTasks->items,
            static fn (AiProcessingTask $task): bool => (string) $task->tenantId === 'tenant-a',
        ))[0];
        $foreign = $harness->messages->findByBatchResponse(
            new TenantId('tenant-b'),
            $tenantA->influencerId,
            $tenantA->messageBatchId,
            AiResponseType::CONVERSATION_TURN,
        );
        self::assertNull($foreign);
    }

    private function harness(): AsyncAiHarness
    {
        return new AsyncAiHarness;
    }
}

final class AsyncAiHarness
{
    public MemoryUsers $users;

    public MemoryConversations $conversations;

    public MemoryMessages $messages;

    public MemoryMessageBatches $batches;

    public MemoryAiProcessingTasks $aiTasks;

    public AsyncAiGateway $gateway;

    public ProcessConversationTurnHandler $turnHandler;

    private ReceiveIncomingMessageBatchHandler $handler;

    public function __construct()
    {
        $this->users = new MemoryUsers;
        $this->conversations = new MemoryConversations;
        $this->messages = new MemoryMessages;
        $this->batches = new MemoryMessageBatches;
        $this->aiTasks = new MemoryAiProcessingTasks;
        $this->gateway = new AsyncAiGateway;
        $events = new RecordedEvents;
        $pipeline = new ProcessMessageAiPipelineHandler(
            new EvaluateMessageRulesHandler(new AiRules([]), new RuleMatcherRegistry([new KeywordRuleMatcher, new RegexRuleMatcher]), $events),
            new BuildConversationContextHandler($this->messages, new EmptyAiMemories, new BuildPersonaContextHandler(new AsyncAiPersonas), new AsyncAiKnowledge),
            new ContextPromptBuilder,
            new GenerateResponseHandler($this->gateway),
            new AsyncAiQuality,
            new FakeQualityChecks,
            $this->messages,
            new AiAdminTasks,
            $events,
        );
        $this->turnHandler = new ProcessConversationTurnHandler($this->aiTasks, $this->batches, $this->messages, $pipeline);
        $dispatcher = new class($this->turnHandler) implements AiProcessingDispatcherInterface
        {
            public function __construct(private ProcessConversationTurnHandler $handler) {}

            public function dispatch(AiProcessingTask $task): void
            {
                $this->handler->handle($task->id(), 1, 1);
            }
        };
        $this->handler = new ReceiveIncomingMessageBatchHandler(
            $this->users,
            $this->conversations,
            $this->messages,
            $this->batches,
            $events,
            $this->aiTasks,
            $dispatcher,
        );
    }

    public function ingest(string $tenantId, string $influencerId, string $messageId, string $text): MessageBatchIngestionResult
    {
        return $this->handler->handle(new ReceiveIncomingMessageBatchCommand(
            new IncomingMessageBatchData(
                'telegram',
                '100',
                'amir',
                $tenantId,
                $influencerId,
                [new IncomingBatchMessageItem($messageId, $text, new DateTimeImmutable('2026-09-08T12:00:00+00:00'))],
            ),
        ));
    }
}

final class AsyncAiGateway implements LlmGatewayInterface
{
    public int $calls = 0;

    public bool $fail = false;

    public function generate(LlmRequest $request): LlmResponse
    {
        $this->calls++;
        if ($this->fail) {
            throw new RuntimeException('Ollama unavailable.');
        }

        return new LlmResponse('Async AI reply', 'fake-model', 8, 3);
    }
}

final class AsyncAiQuality implements QualityCheckerInterface
{
    public function evaluate(string $userMessage, string $aiResponse, ConversationContext $context): QualityResult
    {
        return new QualityResult(true, 0.95, [], 'Approved.');
    }
}

final class AsyncAiPersonas implements PersonaRepositoryInterface
{
    public function findByInfluencer(TenantId $tenantId, InfluencerId $influencerId): ?Persona
    {
        return new Persona(new PersonaId('persona-1'), $tenantId, $influencerId, 'Sofia', 'en', 'warm', 'friendly', 'An AI influencer.', ['Be respectful']);
    }

    public function save(Persona $persona): void {}
}

final class AsyncAiKnowledge implements KnowledgeRetrieverInterface
{
    public function retrieve(
        TenantId $tenantId,
        InfluencerId $influencerId,
        string $query,
        int $limit,
        ?RetrievalFilter $filter = null,
    ): array {
        return [new KnowledgeSearchResult('chunk-1', 'Relevant knowledge', 0.9)];
    }
}
