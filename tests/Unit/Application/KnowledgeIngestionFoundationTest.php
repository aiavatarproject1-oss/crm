<?php

namespace Tests\Unit\Application;

use App\Application\Contracts\KnowledgeChunkRepositoryInterface;
use App\Application\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Contracts\KnowledgeSourceRepositoryInterface;
use App\Application\Knowledge\Chunking\FixedTokenChunker;
use App\Application\Knowledge\Parsers\PlainTextParser;
use App\Application\Knowledge\Services\KnowledgeIngestionService;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeChunk;
use App\Domain\Knowledge\Entities\KnowledgeDocument;
use App\Domain\Knowledge\Entities\KnowledgeSource;
use App\Domain\Knowledge\ValueObjects\KnowledgeChunkId;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentStatus;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceId;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceType;
use App\Domain\Knowledge\ValueObjects\KnowledgeType;
use App\Domain\Tenant\ValueObjects\TenantId;
use PHPUnit\Framework\TestCase;

final class KnowledgeIngestionFoundationTest extends TestCase
{
    private IngestionKnowledgeSources $sources;

    private IngestionKnowledgeDocuments $documents;

    private IngestionKnowledgeChunks $chunks;

    private KnowledgeIngestionService $service;

    protected function setUp(): void
    {
        $this->sources = new IngestionKnowledgeSources;
        $this->documents = new IngestionKnowledgeDocuments;
        $this->chunks = new IngestionKnowledgeChunks;
        $this->service = new KnowledgeIngestionService(
            new PlainTextParser,
            new FixedTokenChunker(chunkSize: 5, overlap: 1),
            $this->sources,
            $this->documents,
            $this->chunks,
        );
    }

    public function test_text_ingestion_creates_document(): void
    {
        $source = $this->textSource('tenant-1', 'inf-1', 'FAQ Source', 'Hello world this is knowledge text for ingestion.');
        $result = $this->service->ingest($source, KnowledgeType::FAQ);

        self::assertTrue($result->created);
        self::assertFalse($result->duplicate);
        self::assertNotNull($result->document);
        self::assertSame(1, $result->document->version);
        self::assertSame(KnowledgeDocumentStatus::ACTIVE, $result->document->status->value);
        self::assertSame((string) $source->id(), (string) $result->document->sourceId);
        self::assertSame($source->checksum, $result->document->checksum);
        self::assertSame(KnowledgeSourceType::TEXT, $result->document->source);
        self::assertTrue($result->source->status()->value === 'INGESTED');
    }

    public function test_chunking_creates_ordered_chunks(): void
    {
        $text = 'one two three four five six seven eight nine ten eleven twelve';
        $result = $this->service->ingest(
            $this->textSource('tenant-1', 'inf-1', 'Chunk Doc', $text),
            KnowledgeType::BACKGROUND,
        );

        self::assertNotEmpty($result->chunks);
        $positions = array_map(static fn (KnowledgeChunk $chunk): int => $chunk->position, $result->chunks);
        self::assertSame(range(0, count($result->chunks) - 1), $positions);
        self::assertSame(1, $result->chunks[0]->documentVersion);
        self::assertLessThanOrEqual(5, $result->chunks[0]->tokenCount);
    }

    public function test_tenant_isolation(): void
    {
        $payload = 'Shared FAQ content about shipping policy for customers.';
        $a = $this->service->ingest($this->textSource('tenant-a', 'inf-1', 'FAQ', $payload), KnowledgeType::FAQ);
        $b = $this->service->ingest($this->textSource('tenant-b', 'inf-1', 'FAQ', $payload), KnowledgeType::FAQ);

        self::assertTrue($a->created);
        self::assertTrue($b->created);
        self::assertSame('tenant-a', (string) $a->document?->tenantId);
        self::assertSame('tenant-b', (string) $b->document?->tenantId);

        $foundInA = $this->documents->findByChecksum(new TenantId('tenant-a'), new InfluencerId('inf-1'), $a->source->checksum);
        $crossTenant = $this->documents->findByChecksum(new TenantId('tenant-a'), new InfluencerId('inf-1'), $b->source->checksum);

        self::assertSame((string) $a->document?->id(), (string) $foundInA?->id());
        // Same payload checksum exists in tenant-a only for tenant-a's document; tenant-b uses same hash but different scope.
        self::assertSame($a->source->checksum, $b->source->checksum);
        self::assertSame((string) $a->document?->id(), (string) $crossTenant?->id());
        self::assertNotSame((string) $a->document?->id(), (string) $b->document?->id());
        self::assertNull($this->documents->findByChecksum(new TenantId('tenant-a'), new InfluencerId('inf-2'), $a->source->checksum));
    }

    public function test_versioning_support(): void
    {
        $documentId = new KnowledgeDocumentId('doc-versioned');
        $first = $this->service->ingest(
            $this->textSource('tenant-1', 'inf-1', 'Policy v1', 'Version one of the policy document content here.'),
            KnowledgeType::POLICY,
            $documentId,
        );
        $second = $this->service->ingest(
            $this->textSource('tenant-1', 'inf-1', 'Policy v2', 'Version two of the policy document content changed.'),
            KnowledgeType::POLICY,
            $documentId,
        );

        self::assertSame(1, $first->document?->version);
        self::assertSame(2, $second->document?->version);
        self::assertSame((string) $documentId, (string) $second->document?->id());
        self::assertSame(2, $this->documents->findLatestVersion(new TenantId('tenant-1'), new InfluencerId('inf-1'), $documentId)?->version);
    }

    public function test_duplicate_checksum_detection(): void
    {
        $payload = 'Identical knowledge payload for checksum duplicate detection test.';
        $first = $this->service->ingest($this->textSource('tenant-1', 'inf-1', 'Doc', $payload), KnowledgeType::FAQ);
        $second = $this->service->ingest($this->textSource('tenant-1', 'inf-1', 'Doc again', $payload), KnowledgeType::FAQ);

        self::assertTrue($first->created);
        self::assertFalse($second->created);
        self::assertTrue($second->duplicate);
        self::assertSame((string) $first->document?->id(), (string) $second->document?->id());
        self::assertCount(1, $this->documents->items);
    }

    private function textSource(string $tenantId, string $influencerId, string $name, string $payload): KnowledgeSource
    {
        return KnowledgeSource::create(
            new KnowledgeSourceId(bin2hex(random_bytes(8))),
            new TenantId($tenantId),
            new InfluencerId($influencerId),
            new KnowledgeSourceType(KnowledgeSourceType::TEXT),
            $name,
            $payload,
        );
    }
}

final class IngestionKnowledgeSources implements KnowledgeSourceRepositoryInterface
{
    /** @var array<string, KnowledgeSource> */
    public array $items = [];

    public function save(KnowledgeSource $source): void
    {
        $this->items[(string) $source->id()] = $source;
    }

    public function findById(TenantId $tenantId, InfluencerId $influencerId, KnowledgeSourceId $sourceId): ?KnowledgeSource
    {
        $source = $this->items[(string) $sourceId] ?? null;
        if ($source === null) {
            return null;
        }
        if ((string) $source->tenantId !== (string) $tenantId || (string) $source->influencerId !== (string) $influencerId) {
            return null;
        }

        return $source;
    }

    public function findByChecksum(TenantId $tenantId, InfluencerId $influencerId, string $checksum): ?KnowledgeSource
    {
        foreach ($this->items as $source) {
            if ((string) $source->tenantId === (string) $tenantId
                && (string) $source->influencerId === (string) $influencerId
                && $source->checksum === $checksum) {
                return $source;
            }
        }

        return null;
    }
}

final class IngestionKnowledgeDocuments implements KnowledgeDocumentRepositoryInterface
{
    /** @var array<string, KnowledgeDocument> */
    public array $items = [];

    public function findByInfluencer(TenantId $tenantId, InfluencerId $influencerId): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (KnowledgeDocument $document): bool => (string) $document->tenantId === (string) $tenantId
                && (string) $document->influencerId === (string) $influencerId,
        ));
    }

    public function findVersion(TenantId $tenantId, InfluencerId $influencerId, KnowledgeDocumentId $documentId, int $version): ?KnowledgeDocument
    {
        foreach ($this->items as $document) {
            if ((string) $document->tenantId === (string) $tenantId
                && (string) $document->influencerId === (string) $influencerId
                && (string) $document->id() === (string) $documentId
                && $document->version === $version) {
                return $document;
            }
        }

        return null;
    }

    public function findLatestVersion(TenantId $tenantId, InfluencerId $influencerId, KnowledgeDocumentId $documentId): ?KnowledgeDocument
    {
        $latest = null;
        foreach ($this->findByInfluencer($tenantId, $influencerId) as $document) {
            if ((string) $document->id() !== (string) $documentId) {
                continue;
            }
            if ($latest === null || $document->version > $latest->version) {
                $latest = $document;
            }
        }

        return $latest;
    }

    public function findByChecksum(TenantId $tenantId, InfluencerId $influencerId, string $checksum): ?KnowledgeDocument
    {
        $latest = null;
        foreach ($this->findByInfluencer($tenantId, $influencerId) as $document) {
            if ($document->checksum !== $checksum) {
                continue;
            }
            if ($latest === null || $document->version > $latest->version) {
                $latest = $document;
            }
        }

        return $latest;
    }

    public function save(KnowledgeDocument $document): void
    {
        $key = implode(':', [(string) $document->tenantId, (string) $document->influencerId, (string) $document->id(), 'v'.$document->version]);
        $this->items[$key] = $document;
    }
}

final class IngestionKnowledgeChunks implements KnowledgeChunkRepositoryInterface
{
    /** @var array<string, KnowledgeChunk> */
    public array $items = [];

    public function findByDocument(TenantId $tenantId, InfluencerId $influencerId, KnowledgeDocumentId $documentId): array
    {
        $matches = array_values(array_filter(
            $this->items,
            static fn (KnowledgeChunk $chunk): bool => (string) $chunk->tenantId === (string) $tenantId
                && (string) $chunk->influencerId === (string) $influencerId
                && (string) $chunk->documentId === (string) $documentId,
        ));
        usort($matches, static fn (KnowledgeChunk $a, KnowledgeChunk $b): int => $a->position <=> $b->position);

        return $matches;
    }

    public function findByIds(TenantId $tenantId, InfluencerId $influencerId, array $chunkIds): array
    {
        $ids = array_map(static fn (KnowledgeChunkId $id): string => (string) $id, $chunkIds);

        return array_values(array_filter(
            $this->items,
            static fn (KnowledgeChunk $chunk): bool => in_array((string) $chunk->id(), $ids, true)
                && (string) $chunk->tenantId === (string) $tenantId
                && (string) $chunk->influencerId === (string) $influencerId,
        ));
    }

    public function save(KnowledgeChunk $chunk): void
    {
        $this->items[(string) $chunk->id()] = $chunk;
    }
}
