<?php

namespace Tests\Unit\Application;

use App\Application\AI\Contracts\LlmGatewayInterface;
use App\Application\AI\DTO\LlmRequest;
use App\Application\AI\DTO\LlmResponse;
use App\Application\Contracts\MemoryRepositoryInterface;
use App\Application\Exceptions\ApplicationException;
use App\Application\Memory\Contracts\MemoryDuplicateDetectorInterface;
use App\Application\Memory\Contracts\MemoryExtractionGatewayInterface;
use App\Application\Memory\DTO\ExtractedMemoryCandidateData;
use App\Application\Memory\DTO\MemoryExtractionContext;
use App\Application\Memory\Policies\ThresholdMemoryEvaluationPolicy;
use App\Application\Memory\Prompt\DefaultMemoryExtractionPromptBuilder;
use App\Application\Memory\Services\MemoryEvaluator;
use App\Application\Memory\Services\MemoryExtractionService;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Memory\Entities\MemoryCandidate;
use App\Domain\Memory\ValueObjects\MemoryId;
use App\Domain\Memory\ValueObjects\MemoryStatus;
use App\Domain\Memory\ValueObjects\MemoryType;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\Entities\MessageBatch;
use App\Domain\Message\ValueObjects\ExternalMessageId;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageContent;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Memory\LlmMemoryExtractionGateway;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MemoryExtractionEngineTest extends TestCase
{
    public function test_valid_extraction_creates_candidates(): void
    {
        $gateway = new FakeExtractionGateway([
            new ExtractedMemoryCandidateData(MemoryType::PREFERENCE, 'Likes tea', 0.91, 0.8, ['k' => 1]),
            new ExtractedMemoryCandidateData(MemoryType::FACT, 'Lives in Tehran', 0.88, 0.75),
        ]);
        $service = new MemoryExtractionService($gateway);
        $context = $this->context();

        $candidates = $service->extract($context);

        self::assertCount(2, $candidates);
        self::assertContainsOnlyInstancesOf(MemoryCandidate::class, $candidates);
        self::assertTrue($candidates[0]->status()->isPending());
        self::assertSame('Likes tea', $candidates[0]->content);
        self::assertSame((string) $context->batchId, (string) $candidates[0]->sourceMessageBatchId);
        self::assertSame((string) $context->tenantId, (string) $candidates[0]->tenantId);
    }

    public function test_invalid_ai_response_is_rejected(): void
    {
        $llm = new class implements LlmGatewayInterface
        {
            public function generate(LlmRequest $request): LlmResponse
            {
                return new LlmResponse('this is not json', 'fake', 1, 1);
            }
        };
        $gateway = new LlmMemoryExtractionGateway($llm, new DefaultMemoryExtractionPromptBuilder);
        $service = new MemoryExtractionService($gateway);

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Memory extraction failed: invalid AI output.');
        $service->extract($this->context());
    }

    public function test_extraction_never_creates_memory_directly(): void
    {
        $memories = new class implements MemoryRepositoryInterface
        {
            public int $saveCalls = 0;

            public function save(Memory $memory): void
            {
                $this->saveCalls++;
            }

            public function findById(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, MemoryId $memoryId): ?Memory
            {
                return null;
            }

            public function findActiveForUser(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, int $limit = 50): array
            {
                return [];
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
                return [];
            }
        };

        $service = new MemoryExtractionService(new FakeExtractionGateway([
            new ExtractedMemoryCandidateData(MemoryType::FACT, 'Has a dog', 0.9, 0.7),
        ]));
        $candidates = $service->extract($this->context());
        self::assertCount(1, $candidates);

        $evaluator = new MemoryEvaluator(new ThresholdMemoryEvaluationPolicy(new class implements MemoryDuplicateDetectorInterface
        {
            public function isDuplicate(MemoryCandidate $candidate): bool
            {
                return false;
            }
        }));
        $evaluator->evaluate($candidates[0]);

        self::assertSame(0, $memories->saveCalls);
        self::assertTrue($candidates[0]->status()->isApproved());
    }

    public function test_batch_messages_are_included_in_extraction_context(): void
    {
        $batch = MessageBatch::create(
            new MessageBatchId('batch-1'),
            new TenantId('tenant-a'),
            new InfluencerId('influencer-1'),
            new UserId('user-1'),
            new ConversationId('conversation-1'),
            new MessagePlatform('telegram'),
            [new MessageId('m-1'), new MessageId('m-2')],
        );
        $messages = [
            $this->message('m-1', 'سلام'),
            $this->message('m-2', 'خوبی؟'),
            $this->message('m-other', 'ignored'),
        ];
        $context = MemoryExtractionContext::fromBatch($batch, $messages);

        self::assertCount(2, $context->messages);
        self::assertSame('سلام', $context->messages[0]['content']);
        self::assertSame('خوبی؟', $context->messages[1]['content']);

        $prompt = (new DefaultMemoryExtractionPromptBuilder)->build($context);
        self::assertStringContainsString('سلام', $prompt->user_prompt);
        self::assertStringContainsString('خوبی؟', $prompt->user_prompt);
        self::assertStringNotContainsString('ignored', $prompt->user_prompt);
        self::assertSame(2, $prompt->metadata['message_count']);
    }

    public function test_tenant_and_influencer_isolation_on_candidates(): void
    {
        $service = new MemoryExtractionService(new FakeExtractionGateway([
            new ExtractedMemoryCandidateData(MemoryType::FACT, 'Shared text', 0.9, 0.7),
        ]));

        $tenantA = $service->extract($this->context(tenantId: 'tenant-a', influencerId: 'inf-a'));
        $tenantB = $service->extract($this->context(tenantId: 'tenant-b', influencerId: 'inf-b'));

        self::assertTrue($tenantA[0]->belongsToScope(new TenantId('tenant-a'), new InfluencerId('inf-a'), new UserId('user-1')));
        self::assertFalse($tenantA[0]->belongsToScope(new TenantId('tenant-b'), new InfluencerId('inf-a'), new UserId('user-1')));
        self::assertTrue($tenantB[0]->belongsToScope(new TenantId('tenant-b'), new InfluencerId('inf-b'), new UserId('user-1')));
        self::assertNotSame((string) $tenantA[0]->tenantId, (string) $tenantB[0]->tenantId);
        self::assertNotSame((string) $tenantA[0]->influencerId, (string) $tenantB[0]->influencerId);
    }

    public function test_empty_extraction_returns_no_candidates(): void
    {
        $service = new MemoryExtractionService(new FakeExtractionGateway([]));
        self::assertSame([], $service->extract($this->context()));
    }

    public function test_malformed_candidates_are_skipped(): void
    {
        $llm = new class implements LlmGatewayInterface
        {
            public function generate(LlmRequest $request): LlmResponse
            {
                return new LlmResponse(json_encode([
                    'candidates' => [
                        ['type' => 'FACT', 'content' => 'Valid', 'confidence_score' => 0.9, 'importance_score' => 0.8],
                        ['type' => 'NOPE', 'content' => 'Bad type', 'confidence_score' => 0.9, 'importance_score' => 0.8],
                        ['content' => 'Missing type', 'confidence_score' => 0.9, 'importance_score' => 0.8],
                        'not-an-object',
                    ],
                ], JSON_THROW_ON_ERROR), 'fake', 1, 1);
            }
        };
        $service = new MemoryExtractionService(new LlmMemoryExtractionGateway($llm, new DefaultMemoryExtractionPromptBuilder));
        $candidates = $service->extract($this->context());

        self::assertCount(1, $candidates);
        self::assertSame('Valid', $candidates[0]->content);
    }

    private function context(string $tenantId = 'tenant-a', string $influencerId = 'influencer-1'): MemoryExtractionContext
    {
        return new MemoryExtractionContext(
            new TenantId($tenantId),
            new InfluencerId($influencerId),
            new UserId('user-1'),
            new MessageBatchId('batch-1'),
            'telegram',
            [
                ['message_id' => 'm-1', 'content' => 'Hello', 'sender' => 'user'],
            ],
            ['conversation_id' => 'conversation-1'],
        );
    }

    private function message(string $id, string $text): Message
    {
        return Message::create(
            new MessageId($id),
            new TenantId('tenant-a'),
            new InfluencerId('influencer-1'),
            new ConversationId('conversation-1'),
            'user',
            new MessageContent($text),
            new ExternalMessageId('ext-'.$id),
            new MessagePlatform('telegram'),
            new DateTimeImmutable('2026-09-07T22:10:00+00:00'),
        );
    }
}

final class FakeExtractionGateway implements MemoryExtractionGatewayInterface
{
    /**
     * @param  list<ExtractedMemoryCandidateData>  $items
     */
    public function __construct(private array $items) {}

    public function extract(MemoryExtractionContext $context): array
    {
        return $this->items;
    }
}
