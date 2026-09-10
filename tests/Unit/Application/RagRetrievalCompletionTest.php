<?php

namespace Tests\Unit\Application;

use App\Application\AI\Contracts\EmbeddingProviderInterface;
use App\Application\AI\DTO\EmbeddingResult;
use App\Application\Contracts\KnowledgeChunkRepositoryInterface;
use App\Application\RAG\Contracts\VectorStoreInterface;
use App\Application\RAG\DTO\RetrievalFilter;
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
use PHPUnit\Framework\TestCase;

final class RagRetrievalCompletionTest extends TestCase
{
    public function test_tenant_isolation(): void
    {
        $store = new RetrievalInMemoryVectorStore;
        $store->store($this->vector('v1', 'tenant-a', 'inf-1', 'chunk-a', [1.0, 0.0]));
        $store->store($this->vector('v2', 'tenant-b', 'inf-1', 'chunk-b', [1.0, 0.0]));

        $chunks = new RetrievalInMemoryChunks([
            $this->chunk('chunk-a', 'tenant-a', 'inf-1', 'doc-1', 'Tenant A knowledge', 10),
            $this->chunk('chunk-b', 'tenant-b', 'inf-1', 'doc-1', 'Tenant B knowledge', 10),
        ]);

        $results = $this->service($store, $chunks)->retrieve(
            new TenantId('tenant-a'),
            new InfluencerId('inf-1'),
            'query',
            5,
        );

        self::assertCount(1, $results);
        self::assertSame('chunk-a', $results[0]->chunkId);
        self::assertSame('Tenant A knowledge', $results[0]->content);
    }

    public function test_score_threshold(): void
    {
        $store = new RetrievalInMemoryVectorStore;
        $store->store($this->vector('v1', 'tenant-1', 'inf-1', 'chunk-high', [1.0, 0.0]));
        $store->store($this->vector('v2', 'tenant-1', 'inf-1', 'chunk-low', [0.1, 0.9]));

        $chunks = new RetrievalInMemoryChunks([
            $this->chunk('chunk-high', 'tenant-1', 'inf-1', 'doc-1', 'Strong match', 5),
            $this->chunk('chunk-low', 'tenant-1', 'inf-1', 'doc-1', 'Weak match', 5),
        ]);

        $results = $this->service($store, $chunks, minScore: 0.5)->retrieve(
            new TenantId('tenant-1'),
            new InfluencerId('inf-1'),
            'query',
            5,
        );

        self::assertCount(1, $results);
        self::assertSame('chunk-high', $results[0]->chunkId);
        self::assertGreaterThanOrEqual(0.5, $results[0]->score);
    }

    public function test_context_size_limiting(): void
    {
        $store = new RetrievalInMemoryVectorStore;
        $store->store($this->vector('v1', 'tenant-1', 'inf-1', 'chunk-1', [1.0, 0.0]));
        $store->store($this->vector('v2', 'tenant-1', 'inf-1', 'chunk-2', [0.9, 0.1]));
        $store->store($this->vector('v3', 'tenant-1', 'inf-1', 'chunk-3', [0.8, 0.2]));

        $chunks = new RetrievalInMemoryChunks([
            $this->chunk('chunk-1', 'tenant-1', 'inf-1', 'doc-1', 'First', 80),
            $this->chunk('chunk-2', 'tenant-1', 'inf-1', 'doc-1', 'Second', 80),
            $this->chunk('chunk-3', 'tenant-1', 'inf-1', 'doc-1', 'Third', 80),
        ]);

        $results = $this->service($store, $chunks, maxContextTokens: 160)->retrieve(
            new TenantId('tenant-1'),
            new InfluencerId('inf-1'),
            'query',
            5,
        );

        self::assertCount(2, $results);
        self::assertSame(['chunk-1', 'chunk-2'], array_map(static fn ($r): string => $r->chunkId, $results));
    }

    public function test_metadata_filtering(): void
    {
        $store = new RetrievalInMemoryVectorStore;
        $store->store($this->vector('v1', 'tenant-1', 'inf-1', 'chunk-faq', [1.0, 0.0]));
        $store->store($this->vector('v2', 'tenant-1', 'inf-1', 'chunk-product', [0.99, 0.01]));

        $chunks = new RetrievalInMemoryChunks([
            $this->chunk('chunk-faq', 'tenant-1', 'inf-1', 'doc-faq', 'FAQ text', 10, [
                'source_id' => 'source-1',
                'category' => 'faq',
            ]),
            $this->chunk('chunk-product', 'tenant-1', 'inf-1', 'doc-product', 'Product text', 10, [
                'source_id' => 'source-2',
                'category' => 'product',
            ]),
        ]);

        $byDocument = $this->service($store, $chunks)->retrieve(
            new TenantId('tenant-1'),
            new InfluencerId('inf-1'),
            'query',
            5,
            new RetrievalFilter(documentId: 'doc-faq'),
        );
        $bySource = $this->service($store, $chunks)->retrieve(
            new TenantId('tenant-1'),
            new InfluencerId('inf-1'),
            'query',
            5,
            new RetrievalFilter(sourceId: 'source-2'),
        );
        $byCategory = $this->service($store, $chunks)->retrieve(
            new TenantId('tenant-1'),
            new InfluencerId('inf-1'),
            'query',
            5,
            new RetrievalFilter(category: 'faq'),
        );

        self::assertSame(['chunk-faq'], array_map(static fn ($r): string => $r->chunkId, $byDocument));
        self::assertSame(['chunk-product'], array_map(static fn ($r): string => $r->chunkId, $bySource));
        self::assertSame(['chunk-faq'], array_map(static fn ($r): string => $r->chunkId, $byCategory));
    }

    public function test_empty_retrieval(): void
    {
        $store = new RetrievalInMemoryVectorStore;
        $chunks = new RetrievalInMemoryChunks([]);
        $service = $this->service($store, $chunks);

        self::assertSame([], $service->retrieve(new TenantId('tenant-1'), new InfluencerId('inf-1'), '', 5));
        self::assertSame([], $service->retrieve(new TenantId('tenant-1'), new InfluencerId('inf-1'), '   ', 5));
        self::assertSame([], $service->retrieve(new TenantId('tenant-1'), new InfluencerId('inf-1'), 'query', 0));
        self::assertSame([], $service->retrieve(new TenantId('tenant-1'), new InfluencerId('inf-1'), 'query', 5));
    }

    private function service(
        VectorStoreInterface $store,
        KnowledgeChunkRepositoryInterface $chunks,
        float $minScore = 0.0,
        int $maxContextTokens = 10_000,
    ): RetrievalService {
        return new RetrievalService(
            new RetrievalStaticEmbeddingProvider([1.0, 0.0]),
            new VectorSimilarityRetrievalStrategy($store),
            $chunks,
            $minScore,
            $maxContextTokens,
            candidateMultiplier: 4,
        );
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

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function chunk(
        string $id,
        string $tenant,
        string $influencer,
        string $documentId,
        string $content,
        int $tokenCount,
        array $metadata = [],
    ): KnowledgeChunk {
        return new KnowledgeChunk(
            new KnowledgeChunkId($id),
            new TenantId($tenant),
            new InfluencerId($influencer),
            new KnowledgeDocumentId($documentId),
            $content,
            0,
            $tokenCount,
            $metadata,
        );
    }
}

final readonly class RetrievalStaticEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(private array $vector) {}

    public function embed(string $text): EmbeddingResult
    {
        return new EmbeddingResult($this->vector, 'fake', count($this->vector));
    }
}

final class RetrievalInMemoryVectorStore implements VectorStoreInterface
{
    /** @var array<string, VectorRecord> */
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
        usort($results, static fn (VectorSearchResult $left, VectorSearchResult $right): int => $right->score <=> $left->score);

        return array_slice($results, 0, $limit);
    }
}

final readonly class RetrievalInMemoryChunks implements KnowledgeChunkRepositoryInterface
{
    public function __construct(private array $chunks) {}

    public function findByDocument(TenantId $tenantId, InfluencerId $influencerId, KnowledgeDocumentId $documentId): array
    {
        return [];
    }

    public function findByIds(TenantId $tenantId, InfluencerId $influencerId, array $chunkIds): array
    {
        $ids = array_map('strval', $chunkIds);

        return array_values(array_filter(
            $this->chunks,
            static fn (KnowledgeChunk $chunk): bool => (string) $chunk->tenantId === (string) $tenantId
                && (string) $chunk->influencerId === (string) $influencerId
                && in_array((string) $chunk->id(), $ids, true),
        ));
    }

    public function save(KnowledgeChunk $chunk): void {}
}
