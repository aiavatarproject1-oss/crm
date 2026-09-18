<?php

namespace Tests\Unit\Application;

use App\Application\AI\Commands\GenerateResponseCommand;
use App\Application\AI\Commands\GenerateResponseHandler;
use App\Application\AI\Commands\ProcessConversationTurnHandler;
use App\Application\AI\Contracts\LlmGatewayInterface;
use App\Application\AI\Contracts\MessageAiPipelineInterface;
use App\Application\AI\DTO\LlmRequest;
use App\Application\AI\DTO\LlmResponse;
use App\Application\Exceptions\ApplicationException;
use App\Application\Health\Contracts\HealthCheckInterface;
use App\Application\Health\HealthCheckResult;
use App\Application\Health\HealthCheckService;
use App\Application\Observability\Contracts\StructuredLoggerInterface;
use App\Application\Observability\CorrelationContext;
use App\Domain\AI\Entities\AiProcessingTask;
use App\Domain\AI\ValueObjects\AiProcessingTaskId;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\Entities\Message;
use App\Domain\Message\Entities\MessageBatch;
use App\Domain\Message\ValueObjects\ExternalMessageId;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Message\ValueObjects\MessageContent;
use App\Domain\Message\ValueObjects\MessageId;
use App\Domain\Message\ValueObjects\MessagePlatform;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Observability\LaravelStructuredLogger;
use App\Infrastructure\Observability\StructuredLogMetricsCollector;
use App\Jobs\ProcessConversationTurnJob;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

final class ProductionHardeningTest extends TestCase
{
    public function test_mongo_unavailable_handling(): void
    {
        $service = new HealthCheckService([
            new FakeHealthCheck('mongodb', false, 'MongoDB unavailable.'),
            new FakeHealthCheck('redis', true, 'ok'),
        ]);

        $report = $service->run();

        self::assertFalse($report['healthy']);
        self::assertFalse($report['checks'][0]['healthy']);
        self::assertSame('MongoDB unavailable.', $report['checks'][0]['message']);
        self::assertArrayNotHasKey('dsn', $report['checks'][0]['meta']);
        self::assertArrayNotHasKey('password', $report['checks'][0]['meta']);
    }

    public function test_redis_unavailable_handling(): void
    {
        $service = new HealthCheckService([
            new FakeHealthCheck('redis', false, 'Redis unavailable.'),
            new FakeHealthCheck('queue', true, 'ok'),
        ]);

        $report = $service->run();

        self::assertFalse($report['healthy']);
        self::assertSame('redis', $report['checks'][0]['name']);
        self::assertFalse($report['checks'][0]['healthy']);
        self::assertSame('Redis unavailable.', $report['checks'][0]['message']);
    }

    public function test_llm_failure_handling(): void
    {
        $logger = new RecordingLogger;
        $metrics = new StructuredLogMetricsCollector($logger, new CorrelationContext);
        $gateway = new class implements LlmGatewayInterface
        {
            public function generate(LlmRequest $request): LlmResponse
            {
                throw new RuntimeException('secret provider stack / api-key=sk-live-secret');
            }
        };

        $handler = new GenerateResponseHandler($gateway, $logger, $metrics);

        try {
            $handler->handle(new GenerateResponseCommand(
                new LlmRequest('conversation-1', [['role' => 'user', 'content' => 'hi']], 'system'),
            ));
            self::fail('Expected ApplicationException');
        } catch (ApplicationException $exception) {
            self::assertSame('LLM response generation failed.', $exception->getMessage());
            self::assertStringNotContainsString('sk-live-secret', $exception->getMessage());
        }

        self::assertTrue($logger->hasEvent('llm.call.failed'));
        self::assertFalse($logger->contextContains('sk-live-secret'));
        self::assertNotEmpty(array_filter(
            $metrics->recorded,
            static fn (array $row): bool => $row['name'] === 'llm.failures',
        ));
    }

    public function test_queue_failure_handling(): void
    {
        $tenant = new TenantId('tenant-1');
        $influencer = new InfluencerId('inf-1');
        $conversation = new ConversationId('conv-1');
        $batchId = new MessageBatchId('batch-1');
        $messageId = new MessageId('message-1');

        $tasks = new MemoryAiProcessingTasks;
        $batches = new MemoryMessageBatches;
        $messages = new MemoryMessages;

        $batches->save(MessageBatch::create(
            $batchId,
            $tenant,
            $influencer,
            new UserId('user-1'),
            $conversation,
            new MessagePlatform('telegram'),
            [$messageId],
        ));
        $messages->save(Message::create(
            $messageId,
            $tenant,
            $influencer,
            $conversation,
            'user',
            new MessageContent('hello'),
            new ExternalMessageId('ext-1'),
            new MessagePlatform('telegram'),
            null,
            'incoming',
            'text',
            [],
            $batchId,
        ));
        $tasks->save(AiProcessingTask::create(
            new AiProcessingTaskId('task-1'),
            $tenant,
            $influencer,
            $conversation,
            $batchId,
        ));

        $logger = new RecordingLogger;
        $metrics = new StructuredLogMetricsCollector($logger, new CorrelationContext);
        $handler = new ProcessConversationTurnHandler(
            $tasks,
            $batches,
            $messages,
            new class implements MessageAiPipelineInterface
            {
                public function process(Message $message, UserId $userId, array $metadata = []): \App\Application\AI\DTO\AiPipelineResult
                {
                    throw new ApplicationException('LLM response generation failed.');
                }
            },
            $logger,
            $metrics,
        );

        try {
            $handler->handle(new AiProcessingTaskId('task-1'), 1, 1);
            self::fail('Expected ApplicationException');
        } catch (ApplicationException) {
            // expected
        }

        $stored = $tasks->find(new AiProcessingTaskId('task-1'));
        self::assertNotNull($stored);
        self::assertTrue($stored->status()->isFailed());
        self::assertSame('LLM response generation failed.', $stored->error());
        self::assertTrue($logger->hasEvent('ai.processing.failed'));
        self::assertNotEmpty(array_filter(
            $metrics->recorded,
            static fn (array $row): bool => $row['name'] === 'queue.failures',
        ));
    }

    public function test_no_secret_leakage(): void
    {
        $correlation = new CorrelationContext;
        $correlation->set('corr-secret-test');
        $logger = new LaravelStructuredLogger($correlation);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                self::assertSame('safe message', $message);
                self::assertSame('corr-secret-test', $context['correlation_id']);
                self::assertArrayNotHasKey('api_key', $context);
                self::assertArrayNotHasKey('password', $context);
                self::assertArrayNotHasKey('authorization', $context);
                self::assertArrayNotHasKey('raw_payload', $context);
                self::assertSame('tenant-1', $context['tenant_id']);

                return true;
            });

        $logger->error('safe message', [
            'api_key' => 'sk-should-not-appear',
            'password' => 'hunter2',
            'authorization' => 'Bearer secret',
            'raw_payload' => ['token' => 'x'],
            'tenant_id' => 'tenant-1',
        ]);

        $job = new ProcessConversationTurnJob('task-1', 'corr-from-http');
        self::assertSame('corr-from-http', $job->correlationId);
    }
}

final readonly class FakeHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private string $name,
        private bool $healthy,
        private string $message,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function check(): HealthCheckResult
    {
        return new HealthCheckResult($this->name, $this->healthy, $this->message);
    }
}

final class RecordingLogger implements StructuredLoggerInterface
{
    /** @var list<array{level: string, event: string, context: array<string, mixed>}> */
    public array $entries = [];

    public function info(string $event, array $context = []): void
    {
        $this->entries[] = ['level' => 'info', 'event' => $event, 'context' => $context];
    }

    public function warning(string $event, array $context = []): void
    {
        $this->entries[] = ['level' => 'warning', 'event' => $event, 'context' => $context];
    }

    public function error(string $event, array $context = []): void
    {
        $this->entries[] = ['level' => 'error', 'event' => $event, 'context' => $context];
    }

    public function hasEvent(string $event): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry['event'] === $event) {
                return true;
            }
        }

        return false;
    }

    public function contextContains(string $needle): bool
    {
        foreach ($this->entries as $entry) {
            if (str_contains(json_encode($entry['context'], JSON_THROW_ON_ERROR), $needle)) {
                return true;
            }
        }

        return false;
    }
}
