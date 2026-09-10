<?php

namespace Tests\Unit\Application;

use App\Application\AI\Commands\GenerateResponseHandler;
use App\Application\AI\Commands\ProcessMessageAiPipelineCommand;
use App\Application\AI\Commands\ProcessMessageAiPipelineHandler;
use App\Application\AI\Contracts\LlmGatewayInterface;
use App\Application\AI\DTO\LlmRequest;
use App\Application\AI\DTO\LlmResponse;
use App\Application\AI\Prompt\ContextPromptBuilder;
use App\Application\Context\BuildConversationContextHandler;
use App\Application\Context\BuildPersonaContextHandler;
use App\Application\Context\DTO\ConversationContext;
use App\Application\Contracts\AdminTaskRepositoryInterface;
use App\Application\Contracts\DomainEventPublisherInterface;
use App\Application\Contracts\MemoryRepositoryInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Contracts\PersonaRepositoryInterface;
use App\Application\Contracts\RuleRepositoryInterface;
use App\Application\Exceptions\ApplicationException;
use App\Application\Quality\Contracts\QualityCheckerInterface;
use App\Application\Quality\DTO\QualityResult;
use App\Application\RAG\Contracts\KnowledgeRetrieverInterface;
use App\Application\RAG\DTO\KnowledgeSearchResult;
use App\Application\RAG\DTO\RetrievalFilter;
use App\Application\Rules\EvaluateMessageRulesHandler;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\Entities\Persona;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Influencer\ValueObjects\PersonaId;
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
use App\Domain\Quality\Entities\AdminTask;
use App\Domain\Quality\ValueObjects\ResponseDecision;
use App\Domain\Rule\Entities\Rule;
use App\Domain\Rule\Services\KeywordRuleMatcher;
use App\Domain\Rule\Services\RegexRuleMatcher;
use App\Domain\Rule\Services\RuleMatcherRegistry;
use App\Domain\Rule\ValueObjects\RuleDecision;
use App\Domain\Rule\ValueObjects\RuleId;
use App\Domain\Rule\ValueObjects\RuleType;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AiPipelineTest extends TestCase
{
    public function test_allowed_message_calls_llm_gateway(): void
    {
        [$pipeline, $gateway] = $this->pipeline([]);
        $result = $pipeline->handle(new ProcessMessageAiPipelineCommand($this->message('Hello'), new UserId('user-1')));
        self::assertSame(1, $gateway->calls);
        self::assertSame('Generated reply', $result->response?->content);
    }

    public function test_admin_review_does_not_call_llm(): void
    {
        $rule = $this->reviewRule();
        [$pipeline, $gateway, $messages, $quality] = $this->pipeline([$rule]);
        $result = $pipeline->handle(new ProcessMessageAiPipelineCommand($this->message('Are you an AI?'), new UserId('user-1')));
        self::assertSame(0, $gateway->calls);
        self::assertNull($result->response);
        self::assertCount(0, $messages->saved);
        self::assertSame(0, $quality->calls);
    }

    public function test_rule_rejection_bypasses_llm(): void
    {
        [$pipeline, $gateway, $messages, $quality] = $this->pipeline([$this->blockingRule()]);

        $result = $pipeline->handle(new ProcessMessageAiPipelineCommand($this->message('spam'), new UserId('user-1')));

        self::assertSame(ResponseDecision::REJECTED, $result->decision->value);
        self::assertSame(0, $gateway->calls);
        self::assertSame(0, $quality->calls);
        self::assertCount(0, $messages->saved);
    }

    public function test_llm_response_becomes_outgoing_ai_message(): void
    {
        [$pipeline, , $messages] = $this->pipeline([]);
        $pipeline->handle(new ProcessMessageAiPipelineCommand($this->message('Hello'), new UserId('user-1')));
        self::assertCount(1, $messages->saved);
        self::assertSame('ai', $messages->saved[0]->sender);
        self::assertSame('outgoing', $messages->saved[0]->direction);
        self::assertSame('text', $messages->saved[0]->contentType);
        self::assertSame('Generated reply', $messages->saved[0]->content->value);
    }

    public function test_llm_receives_built_conversation_and_influencer_context(): void
    {
        [$pipeline, $gateway] = $this->pipeline([]);
        $pipeline->handle(new ProcessMessageAiPipelineCommand($this->message('Hello'), new UserId('user-1')));

        self::assertSame([['role' => 'user', 'content' => 'Hello']], $gateway->lastRequest?->messages);
        self::assertSame('Sofia', $gateway->lastRequest?->influencer_context['name']);
        self::assertSame('Likes tea', $gateway->lastRequest?->user_context[0]['content']);
        self::assertSame('Relevant knowledge', $gateway->lastRequest?->knowledge_context[0]['content']);
        self::assertStringContainsString('Sofia', (string) $gateway->lastRequest?->system_prompt);
        self::assertStringContainsString('Likes tea', (string) $gateway->lastRequest?->system_prompt);
        self::assertStringContainsString('Relevant knowledge', (string) $gateway->lastRequest?->system_prompt);
    }

    public function test_quality_failure_creates_admin_task_without_storing_assistant_message(): void
    {
        [$pipeline, , $messages, $quality, $adminTasks] = $this->pipeline([]);
        $quality->approved = false;

        $result = $pipeline->handle(new ProcessMessageAiPipelineCommand($this->message('Hello'), new UserId('user-1')));

        self::assertSame(ResponseDecision::ADMIN_REVIEW, $result->decision->value);
        self::assertNull($result->assistant_message_id);
        self::assertCount(0, $messages->saved);
        self::assertCount(1, $adminTasks->saved);
        self::assertSame('Quality policy failed.', $adminTasks->saved[0]->reason);
    }

    public function test_full_pipeline_success_is_approved(): void
    {
        [$pipeline, $gateway, $messages, $quality, $adminTasks] = $this->pipeline([]);

        $result = $pipeline->handle(new ProcessMessageAiPipelineCommand($this->message('Hello'), new UserId('user-1')));

        self::assertSame(ResponseDecision::APPROVED, $result->decision->value);
        self::assertTrue($result->quality?->approved);
        self::assertSame(1, $gateway->calls);
        self::assertSame(1, $quality->calls);
        self::assertCount(1, $messages->saved);
        self::assertCount(0, $adminTasks->saved);
    }

    public function test_llm_failure_is_wrapped_and_no_message_is_stored(): void
    {
        [$pipeline, $gateway, $messages] = $this->pipeline([]);
        $gateway->fail = true;
        try {
            $pipeline->handle(new ProcessMessageAiPipelineCommand($this->message('Hello'), new UserId('user-1')));
            self::fail('Expected an application exception.');
        } catch (ApplicationException $exception) {
            self::assertSame('LLM response generation failed.', $exception->getMessage());
            self::assertCount(0, $messages->saved);
        }
    }

    public function test_quality_checker_failure_is_wrapped_and_no_message_is_stored(): void
    {
        [$pipeline, , $messages, $quality, $adminTasks] = $this->pipeline([]);
        $quality->fail = true;
        try {
            $pipeline->handle(new ProcessMessageAiPipelineCommand($this->message('Hello'), new UserId('user-1')));
            self::fail('Expected an application exception.');
        } catch (ApplicationException $exception) {
            self::assertSame('Quality evaluation failed.', $exception->getMessage());
            self::assertCount(0, $messages->saved);
            self::assertCount(0, $adminTasks->saved);
        }
    }

    private function pipeline(array $rules): array
    {
        $gateway = new FakeLlmGateway;
        $messages = new AiMessages;
        $quality = new FakeQualityChecker;
        $adminTasks = new AiAdminTasks;
        $events = new AiEvents;
        $evaluator = new EvaluateMessageRulesHandler(new AiRules($rules), new RuleMatcherRegistry([new KeywordRuleMatcher, new RegexRuleMatcher]), $events);

        return [new ProcessMessageAiPipelineHandler($evaluator, new BuildConversationContextHandler($messages, new EmptyAiMemories, new BuildPersonaContextHandler(new AiPersonas), new AiKnowledge), new ContextPromptBuilder, new GenerateResponseHandler($gateway), $quality, $messages, $adminTasks, $events), $gateway, $messages, $quality, $adminTasks];
    }

    private function message(string $content): Message
    {
        return Message::create(new MessageId('message-1'), new TenantId('tenant-1'), new InfluencerId('influencer-1'), new ConversationId('conversation-1'), 'user', new MessageContent($content), new ExternalMessageId('external-1'), new MessagePlatform('telegram'));
    }

    private function reviewRule(): Rule
    {
        return new Rule(new RuleId('rule-1'), new TenantId('tenant-1'), new InfluencerId('influencer-1'), 'identity', new RuleType(RuleType::KEYWORD), ['AI'], 100, new RuleDecision(RuleDecision::ADMIN_REVIEW), true, 1);
    }

    private function blockingRule(): Rule
    {
        return new Rule(new RuleId('rule-2'), new TenantId('tenant-1'), new InfluencerId('influencer-1'), 'spam', new RuleType(RuleType::KEYWORD), ['spam'], 100, new RuleDecision(RuleDecision::BLOCK), true, 1);
    }
}

final class FakeLlmGateway implements LlmGatewayInterface
{
    public int $calls = 0;

    public bool $fail = false;

    public ?LlmRequest $lastRequest = null;

    public function generate(LlmRequest $request): LlmResponse
    {
        $this->calls++;
        $this->lastRequest = $request;
        if ($this->fail) {
            throw new RuntimeException('Ollama unavailable.');
        }

        return new LlmResponse('Generated reply', 'fake-model', 12, 5);
    }
}

final class FakeQualityChecker implements QualityCheckerInterface
{
    public int $calls = 0;

    public bool $approved = true;

    public bool $fail = false;

    public function evaluate(string $userMessage, string $aiResponse, ConversationContext $context): QualityResult
    {
        $this->calls++;
        if ($this->fail) {
            throw new RuntimeException('Quality model unavailable.');
        }

        return new QualityResult($this->approved, $this->approved ? 0.95 : 0.2, $this->approved ? [] : ['unsafe'], $this->approved ? 'Approved.' : 'Quality policy failed.');
    }
}

final class AiAdminTasks implements AdminTaskRepositoryInterface
{
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

final readonly class AiRules implements RuleRepositoryInterface
{
    public function __construct(private array $rules) {}

    public function findEnabledRules(TenantId $tenantId, InfluencerId $influencerId): array
    {
        return $this->rules;
    }

    public function save(Rule $rule): void {}
}

final class AiMessages implements MessageRepositoryInterface
{
    public array $saved = [];

    public function find(MessageId $messageId): ?Message
    {
        foreach ($this->saved as $message) {
            if ((string) $message->id() === (string) $messageId) {
                return $message;
            }
        }

        return null;
    }

    public function findRecentByConversation(TenantId $tenantId, InfluencerId $influencerId, ConversationId $conversationId, int $limit): array
    {
        return [Message::create(new MessageId('context-message'), $tenantId, $influencerId, $conversationId, 'user', new MessageContent('Hello'), new ExternalMessageId('context-external'), new MessagePlatform('telegram'))];
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
        foreach ($this->saved as $message) {
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
        $this->saved[] = $message;
    }
}

final class EmptyAiMemories implements MemoryRepositoryInterface
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

final class AiPersonas implements PersonaRepositoryInterface
{
    public function findByInfluencer(TenantId $tenantId, InfluencerId $influencerId): ?Persona
    {
        return new Persona(new PersonaId('persona-1'), $tenantId, $influencerId, 'Sofia', 'en', 'warm', 'friendly', 'An AI influencer.', ['Be respectful']);
    }

    public function save(Persona $persona): void {}
}

final class AiEvents implements DomainEventPublisherInterface
{
    public function publish(object $event): void {}
}

final class AiKnowledge implements KnowledgeRetrieverInterface
{
    public function retrieve(
        TenantId $tenantId,
        InfluencerId $influencerId,
        string $query,
        int $limit,
        ?RetrievalFilter $filter = null,
    ): array {
        return [new KnowledgeSearchResult('chunk-1', 'Relevant knowledge', 0.9, [
            'document_id' => 'document-1',
        ])];
    }
}
