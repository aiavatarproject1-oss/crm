<?php

namespace App\Providers;

use App\Application\AI\Commands\ProcessMessageAiPipelineHandler;
use App\Application\AI\Contracts\AiProcessingDispatcherInterface;
use App\Application\AI\Contracts\EmbeddingProviderInterface;
use App\Application\AI\Contracts\LlmGatewayInterface;
use App\Application\AI\Contracts\MessageAiPipelineInterface;
use App\Application\AI\Contracts\PromptBuilderInterface;
use App\Application\AI\Prompt\ContextPromptBuilder;
use App\Application\AI\Services\PromptPolicyService;
use App\Application\Context\BuildConversationContextHandler;
use App\Application\Context\BuildPersonaContextHandler;
use App\Application\Context\Services\SalesFunnelStageResolver;
use App\Application\Contracts\AdminTaskRepositoryInterface;
use App\Application\Contracts\AiProcessingTaskRepositoryInterface;
use App\Application\Contracts\ConversationRepositoryInterface;
use App\Application\Contracts\DomainEventPublisherInterface;
use App\Application\Contracts\KnowledgeChunkRepositoryInterface;
use App\Application\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Contracts\KnowledgeSourceRepositoryInterface;
use App\Application\Contracts\MemoryRepositoryInterface;
use App\Application\Contracts\MessageBatchRepositoryInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Contracts\PersonaRepositoryInterface;
use App\Application\Contracts\PromptPolicyRepositoryInterface;
use App\Application\Contracts\RuleRepositoryInterface;
use App\Application\Contracts\UserRepositoryInterface;
use App\Application\Contracts\VectorRecordRepositoryInterface;
use App\Application\Health\HealthCheckService;
use App\Application\Knowledge\Chunking\FixedTokenChunker;
use App\Application\Knowledge\Contracts\ChunkingStrategyInterface;
use App\Application\Knowledge\Contracts\KnowledgeParserInterface;
use App\Application\Knowledge\Parsers\PlainTextParser;
use App\Application\Knowledge\Services\GenerateChunkEmbeddingService;
use App\Application\Knowledge\Services\KnowledgeIngestionService;
use App\Application\Memory\Contracts\MemoryConflictDetectorInterface;
use App\Application\Memory\Contracts\MemoryDuplicateDetectorInterface;
use App\Application\Memory\Contracts\MemoryEvaluationPolicyInterface;
use App\Application\Memory\Contracts\MemoryExtractionGatewayInterface;
use App\Application\Memory\Contracts\MemoryExtractionPromptBuilderInterface;
use App\Application\Memory\Contracts\MemoryExtractorInterface;
use App\Application\Memory\Contracts\MemorySimilarityPolicyInterface;
use App\Application\Memory\Policies\DeterministicMemorySimilarityPolicy;
use App\Application\Memory\Policies\ScopedMemoryConflictDetector;
use App\Application\Memory\Policies\ScopedMemoryDuplicateDetector;
use App\Application\Memory\Policies\ThresholdMemoryEvaluationPolicy;
use App\Application\Memory\Prompt\DefaultMemoryExtractionPromptBuilder;
use App\Application\Memory\Services\MemoryConsolidationService;
use App\Application\Memory\Services\MemoryEvaluator;
use App\Application\Memory\Services\MemoryExtractionService;
use App\Application\Memory\Services\MessageBatchMemoryExtractor;
use App\Application\Observability\Contracts\MetricsCollectorInterface;
use App\Application\Observability\Contracts\StructuredLoggerInterface;
use App\Application\Observability\CorrelationContext;
use App\Application\Quality\Contracts\QualityCheckerInterface;
use App\Application\RAG\Contracts\KnowledgeRetrieverInterface;
use App\Application\RAG\Contracts\RetrievalStrategyInterface;
use App\Application\RAG\Contracts\VectorStoreInterface;
use App\Application\RAG\RetrievalService;
use App\Application\RAG\Strategies\VectorSimilarityRetrievalStrategy;
use App\Infrastructure\AI\Ollama\OllamaEmbeddingProvider;
use App\Infrastructure\AI\Ollama\OllamaLlmGateway;
use App\Infrastructure\AI\Ollama\QwenQualityChecker;
use App\Infrastructure\Events\LaravelDomainEventPublisher;
use App\Infrastructure\Health\MongoHealthCheck;
use App\Infrastructure\Health\OllamaHealthCheck;
use App\Infrastructure\Health\QueueHealthCheck;
use App\Infrastructure\Health\RedisHealthCheck;
use App\Infrastructure\Memory\LlmMemoryExtractionGateway;
use App\Infrastructure\Observability\LaravelStructuredLogger;
use App\Infrastructure\Observability\StructuredLogMetricsCollector;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoAdminTaskRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoAiProcessingTaskRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoConversationRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoKnowledgeChunkRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoKnowledgeDocumentRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoKnowledgeSourceRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoMemoryRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoMessageBatchRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoMessageRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoPersonaRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoPromptPolicyRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoRuleEvaluationRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoUserRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoVectorStore;
use App\Infrastructure\Queue\LaravelAiProcessingDispatcher;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

class InfrastructureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CorrelationContext::class);
        $this->app->singleton(StructuredLoggerInterface::class, LaravelStructuredLogger::class);
        $this->app->singleton(MetricsCollectorInterface::class, StructuredLogMetricsCollector::class);
        $this->app->singleton(HealthCheckService::class, fn ($app): HealthCheckService => new HealthCheckService([
            $app->make(MongoHealthCheck::class),
            $app->make(RedisHealthCheck::class),
            $app->make(OllamaHealthCheck::class),
            $app->make(QueueHealthCheck::class),
        ]));

        $this->app->bind(UserRepositoryInterface::class, MongoUserRepository::class);
        $this->app->bind(AdminTaskRepositoryInterface::class, MongoAdminTaskRepository::class);
        $this->app->bind(PromptPolicyRepositoryInterface::class, MongoPromptPolicyRepository::class);
        $this->app->bind(PromptPolicyService::class, PromptPolicyService::class);
        $this->app->bind(PromptBuilderInterface::class, ContextPromptBuilder::class);
        $this->app->singleton(SalesFunnelStageResolver::class, fn (): SalesFunnelStageResolver => new SalesFunnelStageResolver(
            (int) config('chat.sales_funnel.warmup_max_user_messages', SalesFunnelStageResolver::DEFAULT_WARMUP_MAX_USER_MESSAGES),
            (int) config('chat.sales_funnel.tease_max_user_messages', SalesFunnelStageResolver::DEFAULT_TEASE_MAX_USER_MESSAGES),
            array_values((array) config('chat.sales_funnel.buy_intent_keywords', SalesFunnelStageResolver::DEFAULT_BUY_INTENT_KEYWORDS)),
        ));
        $this->app->bind(BuildConversationContextHandler::class, fn ($app): BuildConversationContextHandler => new BuildConversationContextHandler(
            $app->make(MessageRepositoryInterface::class),
            $app->make(MemoryRepositoryInterface::class),
            $app->make(BuildPersonaContextHandler::class),
            $app->make(KnowledgeRetrieverInterface::class),
            $app->make(SalesFunnelStageResolver::class),
            (bool) config('rag.enabled', true),
        ));
        $this->app->bind(ConversationRepositoryInterface::class, MongoConversationRepository::class);
        $this->app->bind(KnowledgeDocumentRepositoryInterface::class, MongoKnowledgeDocumentRepository::class);
        $this->app->bind(KnowledgeChunkRepositoryInterface::class, MongoKnowledgeChunkRepository::class);
        $this->app->bind(KnowledgeSourceRepositoryInterface::class, MongoKnowledgeSourceRepository::class);
        $this->app->bind(KnowledgeParserInterface::class, PlainTextParser::class);
        $this->app->bind(ChunkingStrategyInterface::class, FixedTokenChunker::class);
        $this->app->bind(KnowledgeIngestionService::class, KnowledgeIngestionService::class);
        $this->app->bind(VectorStoreInterface::class, MongoVectorStore::class);
        $this->app->bind(VectorRecordRepositoryInterface::class, MongoVectorStore::class);
        $this->app->bind(RetrievalStrategyInterface::class, VectorSimilarityRetrievalStrategy::class);
        $this->app->bind(KnowledgeRetrieverInterface::class, fn ($app): RetrievalService => new RetrievalService(
            $app->make(EmbeddingProviderInterface::class),
            $app->make(RetrievalStrategyInterface::class),
            $app->make(KnowledgeChunkRepositoryInterface::class),
            (float) config('rag.min_score'),
            (int) config('rag.max_context_tokens'),
            (int) config('rag.candidate_multiplier'),
            $app->make(StructuredLoggerInterface::class),
            $app->make(MetricsCollectorInterface::class),
        ));
        $this->app->bind(MessageRepositoryInterface::class, MongoMessageRepository::class);
        $this->app->bind(MessageBatchRepositoryInterface::class, MongoMessageBatchRepository::class);
        $this->app->bind(AiProcessingTaskRepositoryInterface::class, MongoAiProcessingTaskRepository::class);
        $this->app->bind(AiProcessingDispatcherInterface::class, LaravelAiProcessingDispatcher::class);
        $this->app->bind(MemoryRepositoryInterface::class, MongoMemoryRepository::class);
        $this->app->bind(MemoryDuplicateDetectorInterface::class, ScopedMemoryDuplicateDetector::class);
        $this->app->bind(MemorySimilarityPolicyInterface::class, DeterministicMemorySimilarityPolicy::class);
        $this->app->bind(MemoryConflictDetectorInterface::class, ScopedMemoryConflictDetector::class);
        $this->app->bind(MemoryEvaluationPolicyInterface::class, ThresholdMemoryEvaluationPolicy::class);
        $this->app->bind(MemoryEvaluator::class, MemoryEvaluator::class);
        $this->app->bind(MemoryConsolidationService::class, MemoryConsolidationService::class);
        $this->app->bind(MemoryExtractionPromptBuilderInterface::class, DefaultMemoryExtractionPromptBuilder::class);
        $this->app->bind(MemoryExtractionGatewayInterface::class, LlmMemoryExtractionGateway::class);
        $this->app->bind(MemoryExtractionService::class, MemoryExtractionService::class);
        $this->app->bind(MemoryExtractorInterface::class, MessageBatchMemoryExtractor::class);
        $this->app->bind(PersonaRepositoryInterface::class, MongoPersonaRepository::class);
        $this->app->bind(RuleRepositoryInterface::class, MongoRuleEvaluationRepository::class);
        $this->app->bind(DomainEventPublisherInterface::class, LaravelDomainEventPublisher::class);
        $this->app->singleton(LlmGatewayInterface::class, fn ($app): OllamaLlmGateway => new OllamaLlmGateway(
            $app->make(Factory::class),
            (string) config('services.ollama.base_url'),
            (string) config('services.ollama.model'),
        ));
        $this->app->singleton(EmbeddingProviderInterface::class, fn ($app): OllamaEmbeddingProvider => new OllamaEmbeddingProvider(
            $app->make(Factory::class),
            (string) config('services.ollama.base_url'),
            (string) config('services.ollama.embedding_model'),
        ));
        $this->app->bind(GenerateChunkEmbeddingService::class, fn ($app): GenerateChunkEmbeddingService => new GenerateChunkEmbeddingService(
            $app->make(EmbeddingProviderInterface::class),
            $app->make(VectorRecordRepositoryInterface::class),
            (string) config('services.ollama.embedding_model'),
        ));
        $this->app->singleton(QualityCheckerInterface::class, fn ($app): QwenQualityChecker => new QwenQualityChecker(
            $app->make(Factory::class),
            (string) config('services.ollama.base_url'),
            (string) config('services.ollama.quality_model'),
        ));
        $this->app->bind(MessageAiPipelineInterface::class, ProcessMessageAiPipelineHandler::class);
    }
}
