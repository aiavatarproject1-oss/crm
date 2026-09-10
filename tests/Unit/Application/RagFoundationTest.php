<?php

namespace Tests\Unit\Application;

use App\Application\AI\Contracts\EmbeddingProviderInterface;
use App\Application\AI\DTO\EmbeddingResult;
use App\Application\Contracts\KnowledgeChunkRepositoryInterface;
use App\Application\RAG\Contracts\VectorStoreInterface;
use App\Application\RAG\DTO\VectorSearchResult;
use App\Application\RAG\RetrievalService;
use App\Application\RAG\Strategies\VectorSimilarityRetrievalStrategy;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeChunk;
use App\Domain\Knowledge\Entities\VectorRecord;
use App\Domain\Knowledge\ValueObjects\KnowledgeChunkId;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Knowledge\ValueObjects\VectorRecordId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\AI\Ollama\OllamaEmbeddingProvider;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RagFoundationTest extends TestCase
{
    public function test_embedding_provider_maps_provider_response(): void
    {
        Http::fake(['http://ollama.test/api/embed' => Http::response(['model' => 'embed-model', 'embeddings' => [[0.1, 0.2, 0.3]]])]);
        $provider = new OllamaEmbeddingProvider($this->app->make(Factory::class), 'http://ollama.test', 'embed-model');

        $result = $provider->embed('hello');

        self::assertSame([0.1, 0.2, 0.3], $result->vector);
        self::assertSame(3, $result->dimensions);
        self::assertSame('embed-model', $result->model);
    }

    public function test_vector_store_saves_and_searches_ranked_records(): void
    {
        $store = new InMemoryVectorStore;
        $store->store($this->vector('vector-1', 'tenant-1', 'influencer-1', 'chunk-1', [1.0, 0.0]));
        $store->store($this->vector('vector-2', 'tenant-1', 'influencer-1', 'chunk-2', [0.2, 0.8]));

        $results = $store->search(new TenantId('tenant-1'), new InfluencerId('influencer-1'), [1.0, 0.0], 2);

        self::assertCount(2, $results);
        self::assertSame('chunk-1', (string) $results[0]->record->chunkId);
        self::assertGreaterThan($results[1]->score, $results[0]->score);
    }

    public function test_vector_search_is_tenant_isolated(): void
    {
        $store = new InMemoryVectorStore;
        $store->store($this->vector('vector-1', 'tenant-1', 'influencer-1', 'chunk-1', [1.0, 0.0]));
        $store->store($this->vector('vector-2', 'tenant-2', 'influencer-1', 'chunk-2', [1.0, 0.0]));

        $results = $store->search(new TenantId('tenant-1'), new InfluencerId('influencer-1'), [1.0, 0.0], 10);

        self::assertSame(['chunk-1'], array_map(fn (VectorSearchResult $result): string => (string) $result->record->chunkId, $results));
    }

    public function test_vector_search_is_influencer_isolated(): void
    {
        $store = new InMemoryVectorStore;
        $store->store($this->vector('vector-1', 'tenant-1', 'influencer-1', 'chunk-1', [1.0, 0.0]));
        $store->store($this->vector('vector-2', 'tenant-1', 'influencer-2', 'chunk-2', [1.0, 0.0]));

        $results = $store->search(new TenantId('tenant-1'), new InfluencerId('influencer-1'), [1.0, 0.0], 10);

        self::assertSame(['chunk-1'], array_map(fn (VectorSearchResult $result): string => (string) $result->record->chunkId, $results));
    }

    public function test_retrieval_returns_knowledge_chunks_in_vector_rank_order(): void
    {
        $store = new InMemoryVectorStore;
        $store->store($this->vector('vector-1', 'tenant-1', 'influencer-1', 'chunk-1', [1.0, 0.0]));
        $store->store($this->vector('vector-2', 'tenant-1', 'influencer-1', 'chunk-2', [0.0, 1.0]));
        $chunks = new InMemoryKnowledgeChunks([
            $this->chunk('chunk-2', 'Second'),
            $this->chunk('chunk-1', 'First'),
        ]);
        $service = new RetrievalService(
            new StaticEmbeddingProvider([0.9, 0.1]),
            new VectorSimilarityRetrievalStrategy($store),
            $chunks,
            minScore: 0.0,
            maxContextTokens: 10_000,
            candidateMultiplier: 1,
        );

        $results = $service->retrieve(new TenantId('tenant-1'), new InfluencerId('influencer-1'), 'query', 2);

        self::assertSame(['First', 'Second'], array_map(fn ($result): string => $result->content, $results));
        self::assertGreaterThan($results[1]->score, $results[0]->score);
    }

    private function vector(string $id, string $tenant, string $influencer, string $chunk, array $embedding): VectorRecord
    {
        return new VectorRecord(
            new VectorRecordId($id),
            new TenantId($tenant),
            new InfluencerId($influencer),
            new KnowledgeChunkId($chunk),
            $embedding,
            count($embedding),
            'fake-model',
            hash('sha256', $id),
        );
    }

    private function chunk(string $id, string $content): KnowledgeChunk
    {
        return new KnowledgeChunk(new KnowledgeChunkId($id), new TenantId('tenant-1'), new InfluencerId('influencer-1'), new KnowledgeDocumentId('document-1'), $content, 0, 2);
    }
}

final readonly class StaticEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(private array $vector) {}

    public function embed(string $text): EmbeddingResult
    {
        return new EmbeddingResult($this->vector, 'fake', count($this->vector));
    }
}

final class InMemoryVectorStore implements VectorStoreInterface
{
    private array $records = [];

    public function store(VectorRecord $record): void
    {
        $this->records[(string) $record->id()] = $record;
    }

    public function search(TenantId $tenantId, InfluencerId $influencerId, array $queryVector, int $limit): array
    {
        $results = [];
        foreach ($this->records as $record) {
            if ((string) $record->tenantId !== (string) $tenantId || (string) $record->influencerId !== (string) $influencerId) {
                continue;
            }
            $dot = $record->vector[0] * $queryVector[0] + $record->vector[1] * $queryVector[1];
            $left = sqrt($record->vector[0] ** 2 + $record->vector[1] ** 2);
            $right = sqrt($queryVector[0] ** 2 + $queryVector[1] ** 2);
            $results[] = new VectorSearchResult($record, $dot / ($left * $right));
        }
        usort($results, fn (VectorSearchResult $left, VectorSearchResult $right): int => $right->score <=> $left->score);

        return array_slice($results, 0, $limit);
    }
}

final readonly class InMemoryKnowledgeChunks implements KnowledgeChunkRepositoryInterface
{
    public function __construct(private array $chunks) {}

    public function findByDocument(TenantId $tenantId, InfluencerId $influencerId, KnowledgeDocumentId $documentId): array
    {
        return [];
    }

    public function findByIds(TenantId $tenantId, InfluencerId $influencerId, array $chunkIds): array
    {
        $ids = array_map('strval', $chunkIds);

        return array_values(array_filter($this->chunks, fn (KnowledgeChunk $chunk): bool => (string) $chunk->tenantId === (string) $tenantId && (string) $chunk->influencerId === (string) $influencerId && in_array((string) $chunk->id(), $ids, true)));
    }

    public function save(KnowledgeChunk $chunk): void {}
}
