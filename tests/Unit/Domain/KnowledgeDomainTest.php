<?php

namespace Tests\Unit\Domain;

use App\Application\Contracts\KnowledgeChunkRepositoryInterface;
use App\Application\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeChunk;
use App\Domain\Knowledge\Entities\KnowledgeDocument;
use App\Domain\Knowledge\ValueObjects\KnowledgeChunkId;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Knowledge\ValueObjects\KnowledgeType;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Mappers\KnowledgeChunkMapper;
use App\Infrastructure\Persistence\MongoDB\Mappers\KnowledgeDocumentMapper;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoKnowledgeChunkRepository;
use App\Infrastructure\Persistence\MongoDB\Repositories\MongoKnowledgeDocumentRepository;
use PHPUnit\Framework\TestCase;

final class KnowledgeDomainTest extends TestCase
{
    public function test_it_creates_a_knowledge_document(): void
    {
        $document = $this->document('tenant-1', 'influencer-1', 'document-1', 1);

        self::assertSame('document-1', (string) $document->id());
        self::assertSame(KnowledgeType::FAQ, $document->type);
        self::assertSame(1, $document->version);
    }

    public function test_document_mapping_preserves_tenant_isolation(): void
    {
        $mapper = new KnowledgeDocumentMapper;
        $first = $mapper->toDocument($this->document('tenant-1', 'influencer-1', 'document-1', 1));
        $second = $mapper->toDocument($this->document('tenant-2', 'influencer-1', 'document-1', 1));

        self::assertNotSame($first->getAttribute('_id'), $second->getAttribute('_id'));
        self::assertSame('tenant-1', $first->tenant_id);
        self::assertSame('tenant-2', $second->tenant_id);
    }

    public function test_document_mapping_preserves_influencer_isolation(): void
    {
        $mapper = new KnowledgeDocumentMapper;
        $first = $mapper->toDocument($this->document('tenant-1', 'influencer-1', 'document-1', 1));
        $second = $mapper->toDocument($this->document('tenant-1', 'influencer-2', 'document-1', 1));

        self::assertNotSame($first->getAttribute('_id'), $second->getAttribute('_id'));
        self::assertSame('influencer-1', $first->influencer_id);
        self::assertSame('influencer-2', $second->influencer_id);
    }

    public function test_multiple_document_versions_keep_the_same_domain_identity(): void
    {
        $mapper = new KnowledgeDocumentMapper;
        $versionOne = $mapper->toDocument($this->document('tenant-1', 'influencer-1', 'document-1', 1));
        $versionTwo = $mapper->toDocument($this->document('tenant-1', 'influencer-1', 'document-1', 2));

        self::assertSame($versionOne->document_id, $versionTwo->document_id);
        self::assertNotSame($versionOne->getAttribute('_id'), $versionTwo->getAttribute('_id'));
    }

    public function test_chunk_references_the_correct_document(): void
    {
        $chunk = new KnowledgeChunk(new KnowledgeChunkId('chunk-1'), new TenantId('tenant-1'), new InfluencerId('influencer-1'), new KnowledgeDocumentId('document-1'), 'Chunk', 0, 12);
        $mapped = (new KnowledgeChunkMapper)->toDomain((new KnowledgeChunkMapper)->toDocument($chunk));

        self::assertSame('document-1', (string) $mapped->documentId);
        self::assertSame('chunk-1', (string) $mapped->id());
    }

    public function test_repository_mappers_round_trip_and_adapters_implement_contracts(): void
    {
        $document = $this->document('tenant-1', 'influencer-1', 'document-1', 2);
        $mapped = (new KnowledgeDocumentMapper)->toDomain((new KnowledgeDocumentMapper)->toDocument($document));

        self::assertSame((string) $document->id(), (string) $mapped->id());
        self::assertSame($document->version, $mapped->version);
        self::assertSame($document->metadata, $mapped->metadata);
        self::assertContains(KnowledgeDocumentRepositoryInterface::class, class_implements(MongoKnowledgeDocumentRepository::class));
        self::assertContains(KnowledgeChunkRepositoryInterface::class, class_implements(MongoKnowledgeChunkRepository::class));
    }

    private function document(string $tenantId, string $influencerId, string $documentId, int $version): KnowledgeDocument
    {
        return new KnowledgeDocument(new KnowledgeDocumentId($documentId), new TenantId($tenantId), new InfluencerId($influencerId), KnowledgeType::FAQ, 'FAQ', 'Content', $version, 'active', 'manual', ['locale' => 'en']);
    }
}
