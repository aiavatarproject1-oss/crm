<?php

namespace Tests\Feature;

use App\Application\Contracts\AdminTaskRepositoryInterface;
use App\Application\Contracts\AiProcessingTaskRepositoryInterface;
use App\Application\Contracts\ConversationRepositoryInterface;
use App\Application\Contracts\DomainEventPublisherInterface;
use App\Application\Contracts\MemoryRepositoryInterface;
use App\Application\Contracts\MessageBatchRepositoryInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Contracts\PersonaRepositoryInterface;
use App\Application\Contracts\RuleRepositoryInterface;
use App\Application\Contracts\UserRepositoryInterface;
use App\Application\Dev\Services\IngestTextKnowledgeService;
use App\Application\Dev\Services\TriggerMemoryExtractionService;
use App\Application\RAG\Contracts\KnowledgeRetrieverInterface;
use App\Application\RAG\DTO\KnowledgeSearchResult;
use App\Domain\AI\Entities\AiProcessingTask;
use App\Domain\AI\ValueObjects\AiProcessingTaskId;
use App\Domain\AI\ValueObjects\AiProcessingTaskStatus;
use App\Domain\Conversation\Entities\Conversation;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Memory\ValueObjects\MemoryId;
use App\Domain\Memory\ValueObjects\MemoryType;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\ValueObjects\ExternalMessageId;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageContent;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Quality\Entities\AdminTask;
use App\Domain\Quality\ValueObjects\AdminTaskId;
use App\Domain\Rule\Entities\Rule;
use App\Domain\Rule\ValueObjects\RuleDecision;
use App\Domain\Rule\ValueObjects\RuleId;
use App\Domain\Rule\ValueObjects\RuleType;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use App\Jobs\ProcessConversationTurnJob;
use DateTimeImmutable;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

final class PostmanValidationApiTest extends TestCase
{
    public function test_create_tenant_and_influencer(): void
    {
        $personas = Mockery::mock(PersonaRepositoryInterface::class);
        $personas->shouldReceive('save')->once();
        $this->app->instance(PersonaRepositoryInterface::class, $personas);

        $tenant = $this->postJson('/api/v1/dev/tenants', ['name' => 'Acme'])->assertCreated()->json('data');
        self::assertNotEmpty($tenant['tenant_id']);

        $influencer = $this->postJson('/api/v1/dev/influencers', [
            'tenant_id' => $tenant['tenant_id'],
            'name' => 'Sofia',
            'persona' => 'Warm lifestyle coach',
        ])->assertCreated()->json('data');

        self::assertSame($tenant['tenant_id'], $influencer['tenant_id']);
        self::assertNotEmpty($influencer['influencer_id']);
    }

    public function test_create_knowledge_and_retrieve_it(): void
    {
        $ingest = Mockery::mock(IngestTextKnowledgeService::class);
        $ingest->shouldReceive('handle')->once()->with(
            'tenant-1',
            'inf-1',
            'FAQ',
            'Shipping takes 3 days.',
        )->andReturn([
            'source_id' => 'source-1',
            'document_id' => 'doc-1',
            'chunks_count' => 1,
            'vectors_created' => 1,
        ]);
        $this->app->instance(IngestTextKnowledgeService::class, $ingest);

        $retriever = Mockery::mock(KnowledgeRetrieverInterface::class);
        $retriever->shouldReceive('retrieve')->once()->andReturn([
            new KnowledgeSearchResult('chunk-1', 'Shipping takes 3 days', 0.91),
        ]);
        $this->app->instance(KnowledgeRetrieverInterface::class, $retriever);

        $this->postJson('/api/v1/dev/knowledge/text', [
            'tenant_id' => 'tenant-1',
            'influencer_id' => 'inf-1',
            'title' => 'FAQ',
            'content' => 'Shipping takes 3 days.',
        ])->assertCreated()
            ->assertJsonPath('data.document_id', 'doc-1')
            ->assertJsonPath('data.vectors_created', 1);

        $this->getJson('/api/v1/dev/knowledge/search?tenant_id=tenant-1&influencer_id=inf-1&query=shipping')
            ->assertOk()
            ->assertJsonPath('data.0.chunk_id', 'chunk-1')
            ->assertJsonPath('data.0.content', 'Shipping takes 3 days');
    }

    public function test_send_message_batch(): void
    {
        Bus::fake();
        $this->mockInboundPersistence();

        $response = $this->postJson('/api/v1/inbound/messages', [
            'tenant_id' => 'tenant-1',
            'influencer_id' => 'influencer-1',
            'platform' => 'telegram',
            'external_user_id' => '202',
            'username' => 'amir',
            'messages' => [
                ['external_message_id' => 'tg-101', 'text' => 'Hello'],
            ],
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', true);

        self::assertNotEmpty($response->json('data.message_batch_id'));
        self::assertNotEmpty($response->json('data.task_id'));
        self::assertNotEmpty($response->json('data.conversation_id'));
        Bus::assertDispatched(ProcessConversationTurnJob::class);
    }

    public function test_verify_ai_task(): void
    {
        $task = AiProcessingTask::reconstitute(
            new AiProcessingTaskId('task-1'),
            new TenantId('tenant-1'),
            new InfluencerId('inf-1'),
            new ConversationId('conv-1'),
            new MessageBatchId('batch-1'),
            AiProcessingTaskStatus::completed(),
            1,
            null,
            new DateTimeImmutable('2026-09-08T12:00:00+00:00'),
            new DateTimeImmutable('2026-09-08T12:00:05+00:00'),
        );

        $tasks = Mockery::mock(AiProcessingTaskRepositoryInterface::class);
        $tasks->shouldReceive('find')->once()->andReturn($task);
        $this->app->instance(AiProcessingTaskRepositoryInterface::class, $tasks);

        $this->getJson('/api/v1/ai/tasks/task-1')
            ->assertOk()
            ->assertJsonPath('data.id', 'task-1')
            ->assertJsonPath('data.status', 'COMPLETED')
            ->assertJsonPath('data.attempts', 1);
    }

    public function test_verify_memory_extraction(): void
    {
        $extraction = Mockery::mock(TriggerMemoryExtractionService::class);
        $extraction->shouldReceive('handle')->once()->with('batch-1')->andReturn([
            'candidates_created' => 1,
            'memories_created' => 1,
        ]);
        $this->app->instance(TriggerMemoryExtractionService::class, $extraction);

        $memory = Memory::create(
            new MemoryId('memory-1'),
            new TenantId('tenant-1'),
            new InfluencerId('inf-1'),
            new UserId('user-1'),
            new MemoryType(MemoryType::PREFERENCE),
            'Likes tea',
            0.9,
            0.8,
        );

        $memories = Mockery::mock(MemoryRepositoryInterface::class);
        $memories->shouldReceive('findActiveForUser')->once()->andReturn([$memory]);
        $this->app->instance(MemoryRepositoryInterface::class, $memories);

        $this->postJson('/api/v1/dev/memory/extract/batch-1')
            ->assertOk()
            ->assertJsonPath('data.candidates_created', 1)
            ->assertJsonPath('data.memories_created', 1);

        $this->getJson('/api/v1/memory/users/user-1?tenant_id=tenant-1&influencer_id=inf-1')
            ->assertOk()
            ->assertJsonPath('data.0.type', MemoryType::PREFERENCE)
            ->assertJsonPath('data.0.content', 'Likes tea');
    }

    public function test_verify_conversation_history(): void
    {
        $conversation = Conversation::start(
            new ConversationId('conv-1'),
            new TenantId('tenant-1'),
            new InfluencerId('inf-1'),
            new UserId('user-1'),
            'telegram',
        );
        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('find')->once()->andReturn($conversation);
        $this->app->instance(ConversationRepositoryInterface::class, $conversations);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('findRecentByConversation')->once()->andReturn([
            Message::create(
                new MessageId('m1'),
                new TenantId('tenant-1'),
                new InfluencerId('inf-1'),
                new ConversationId('conv-1'),
                'user',
                new MessageContent('Hello'),
                new ExternalMessageId('ext-1'),
                new MessagePlatform('telegram'),
            ),
            Message::create(
                new MessageId('m2'),
                new TenantId('tenant-1'),
                new InfluencerId('inf-1'),
                new ConversationId('conv-1'),
                'ai',
                new MessageContent('Hi there'),
                new ExternalMessageId('ai-1'),
                new MessagePlatform('telegram'),
                null,
                'outgoing',
            ),
        ]);
        $this->app->instance(MessageRepositoryInterface::class, $messages);

        $this->getJson('/api/v1/conversations/conv-1/messages?tenant_id=tenant-1&influencer_id=inf-1')
            ->assertOk()
            ->assertJsonPath('data.0.role', 'user')
            ->assertJsonPath('data.1.role', 'assistant')
            ->assertJsonPath('data.1.content', 'Hi there');
    }

    public function test_verify_rule_execution(): void
    {
        $rules = Mockery::mock(RuleRepositoryInterface::class);
        $rules->shouldReceive('save')->once();
        $rules->shouldReceive('findEnabledRules')->once()->andReturn([
            new Rule(
                new RuleId('rule-1'),
                new TenantId('tenant-1'),
                new InfluencerId('inf-1'),
                'block spam',
                new RuleType(RuleType::KEYWORD),
                ['spam'],
                100,
                new RuleDecision(RuleDecision::BLOCK),
                true,
                1,
            ),
        ]);
        $this->app->instance(RuleRepositoryInterface::class, $rules);

        $admin = Mockery::mock(AdminTaskRepositoryInterface::class);
        $admin->shouldReceive('findPending')->once()->andReturn([
            new AdminTask(
                new AdminTaskId('admin-1'),
                new TenantId('tenant-1'),
                new InfluencerId('inf-1'),
                new ConversationId('conv-1'),
                new MessageId('msg-1'),
                'Quality policy failed.',
            ),
        ]);
        $this->app->instance(AdminTaskRepositoryInterface::class, $admin);

        $this->postJson('/api/v1/dev/rules', [
            'tenant_id' => 'tenant-1',
            'influencer_id' => 'inf-1',
            'type' => 'KEYWORD',
            'pattern' => 'spam',
            'decision' => 'BLOCK',
            'priority' => 100,
        ])->assertCreated()->assertJsonPath('data.decision', 'BLOCK');

        $this->getJson('/api/v1/dev/rules?tenant_id=tenant-1&influencer_id=inf-1')
            ->assertOk()
            ->assertJsonPath('data.0.patterns.0', 'spam');

        $this->getJson('/api/v1/admin/tasks?tenant_id=tenant-1&influencer_id=inf-1')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'pending');
    }

    private function mockInboundPersistence(): void
    {
        foreach ([
            UserRepositoryInterface::class,
            ConversationRepositoryInterface::class,
            MessageRepositoryInterface::class,
            MessageBatchRepositoryInterface::class,
            DomainEventPublisherInterface::class,
        ] as $contract) {
            $this->app->instance($contract, Mockery::mock($contract)->shouldIgnoreMissing());
        }

        $aiTasks = Mockery::mock(AiProcessingTaskRepositoryInterface::class)->shouldIgnoreMissing();
        $aiTasks->shouldReceive('save')->once();
        $this->app->instance(AiProcessingTaskRepositoryInterface::class, $aiTasks);

        $this->app->make(MessageRepositoryInterface::class)->shouldReceive('findByExternalIdentity')->once()->andReturnNull();
        $this->app->make(UserRepositoryInterface::class)->shouldReceive('findByPlatformIdentity')->twice()->andReturnNull();
        $this->app->make(ConversationRepositoryInterface::class)->shouldReceive('findActive')->once()->andReturnNull();
        $this->app->make(MessageBatchRepositoryInterface::class)->shouldReceive('save')->once();
    }
}
