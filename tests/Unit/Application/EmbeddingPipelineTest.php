<?php

namespace Tests\Unit\Application;

use App\Application\AI\Contracts\EmbeddingProviderInterface;
use App\Application\AI\DTO\EmbeddingResult;
use App\Application\Contracts\VectorRecordRepositoryInterface;
use App\Application\Exceptions\ApplicationException;
use App\Application\Knowledge\Services\GenerateChunkEmbeddingService;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeChunk;
use App\Domain\Knowledge\Entities\VectorRecord;
use App\Domain\Knowledge\ValueObjects\KnowledgeChunkId;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Tenant\ValueObjects\TenantId;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EmbeddingPipelineTest extends TestCase
{
    public function test_generate_embedding(): void
    {
        $vectors = new InMemoryVectorRecordRepository;
        $service = new GenerateChunkEmbeddingService(
            new PipelineStaticEmbeddingProvider([0.1, 0.2, 0.3]),
            $vectors,
            'model-a',
        );
        $chunk = $this->chunk('chunk-1', 'tenant-1', 'inf-1', 'Hello knowledge chunk');

        $result = $service->generate($chunk);

        self::assertTrue($result->created);
        self::assertFalse($result->duplicate);
        self::assertSame([0.1, 0.2, 0.3], $result->record->vector);
        self::assertSame(3, $result->record->dimensions);
        self::assertSame('model-a', $result->record->embeddingModel);
        self::assertSame(hash('sha256', 'Hello knowledge chunk'), $result->record->contentHash);
        self::assertCount(1, $vectors->items);
    }

    public function test_duplicate_chunk_does_not_create_duplicate_vector(): void
    {
        $vectors = new InMemoryVectorRecordRepository;
        $provider = new CountingEmbeddingProvider([1.0, 0.0]);
        $service = new GenerateChunkEmbeddingService($provider, $vectors, 'model-a');
        $chunk = $this->chunk('chunk-1', 'tenant-1', 'inf-1', 'Same content');

        $first = $service->generate($chunk);
        $second = $service->generate($chunk);

        self::assertTrue($first->created);
        self::assertTrue($second->duplicate);
        self::assertFalse($second->created);
        self::assertSame((string) $first->record->id(), (string) $second->record->id());
        self::assertCount(1, $vectors->items);
        self::assertSame(1, $provider->calls);
    }

    public function test_different_embedding_models_create_separate_vectors(): void
    {
        $vectors = new InMemoryVectorRecordRepository;
        $chunk = $this->chunk('chunk-1', 'tenant-1', 'inf-1', 'Shared text');

        $a = (new GenerateChunkEmbeddingService(new PipelineStaticEmbeddingProvider([1.0, 0.0]), $vectors, 'model-a'))->generate($chunk);
        $b = (new GenerateChunkEmbeddingService(new PipelineStaticEmbeddingProvider([0.0, 1.0]), $vectors, 'model-b'))->generate($chunk);

        self::assertTrue($a->created);
        self::assertTrue($b->created);
        self::assertNotSame((string) $a->record->id(), (string) $b->record->id());
        self::assertCount(2, $vectors->findByChunk(new TenantId('tenant-1'), new InfluencerId('inf-1'), new KnowledgeChunkId('chunk-1')));
    }

    public function test_tenant_isolation(): void
    {
        $vectors = new InMemoryVectorRecordRepository;
        $service = new GenerateChunkEmbeddingService(new PipelineStaticEmbeddingProvider([0.5, 0.5]), $vectors, 'model-a');

        $service->generate($this->chunk('chunk-1', 'tenant-a', 'inf-1', 'Isolated text'));
        $service->generate($this->chunk('chunk-1', 'tenant-b', 'inf-1', 'Isolated text'));

        self::assertCount(1, $vectors->findByChunk(new TenantId('tenant-a'), new InfluencerId('inf-1'), new KnowledgeChunkId('chunk-1')));
        self::assertCount(1, $vectors->findByChunk(new TenantId('tenant-b'), new InfluencerId('inf-1'), new KnowledgeChunkId('chunk-1')));
        self::assertCount(2, $vectors->items);
    }

    public function test_provider_failure_handling(): void
    {
        $service = new GenerateChunkEmbeddingService(
            new FailingEmbeddingProvider,
            new InMemoryVectorRecordRepository,
            'model-a',
        );

        $this->expectException(ApplicationException::class);
        $this->expectExceptionMessage('Embedding generation failed.');
        $service->generate($this->chunk('chunk-1', 'tenant-1', 'inf-1', 'Boom'));
    }

    private function chunk(string $id, string $tenant, string $influencer, string $content): KnowledgeChunk
    {
        return new KnowledgeChunk(
            new KnowledgeChunkId($id),
            new TenantId($tenant),
            new InfluencerId($influencer),
            new KnowledgeDocumentId('doc-1'),
            $content,
            0,
            str_word_count($content),
        );
    }
}

final readonly class PipelineStaticEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(private array $vector) {}

    public function embed(string $text): EmbeddingResult
    {
        return new EmbeddingResult($this->vector, 'fake', count($this->vector));
    }
}

final class CountingEmbeddingProvider implements EmbeddingProviderInterface
{
    public int $calls = 0;

    public function __construct(private array $vector) {}

    public function embed(string $text): EmbeddingResult
    {
        $this->calls++;

        return new EmbeddingResult($this->vector, 'fake', count($this->vector));
    }
}

final class FailingEmbeddingProvider implements EmbeddingProviderInterface
{
    public function embed(string $text): EmbeddingResult
    {
        throw new RuntimeException('provider down');
    }
}

final class InMemoryVectorRecordRepository implements VectorRecordRepositoryInterface
{
    /** @var array<string, VectorRecord> */
    public array $items = [];

    public function save(VectorRecord $record): void
    {
        $this->items[(string) $record->id()] = $record;
    }

    public function findByIdempotencyKey(
        TenantId $tenantId,
        InfluencerId $influencerId,
        KnowledgeChunkId $chunkId,
        string $embeddingModel,
        string $contentHash,
    ): ?VectorRecord {
        foreach ($this->items as $record) {
            if ((string) $record->tenantId === (string) $tenantId
                && (string) $record->influencerId === (string) $influencerId
                && (string) $record->chunkId === (string) $chunkId
                && $record->embeddingModel === $embeddingModel
                && $record->contentHash === $contentHash) {
                return $record;
            }
        }

        return null;
    }

    public function findByChunk(TenantId $tenantId, InfluencerId $influencerId, KnowledgeChunkId $chunkId): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (VectorRecord $record): bool => (string) $record->tenantId === (string) $tenantId
                && (string) $record->influencerId === (string) $influencerId
                && (string) $record->chunkId === (string) $chunkId,
        ));
    }
}
