<?php

namespace App\Application\Dev\Services;

use App\Application\Contracts\KnowledgeChunkRepositoryInterface;
use App\Application\Exceptions\ApplicationException;
use App\Application\Knowledge\Services\GenerateChunkEmbeddingService;
use App\Application\Knowledge\Services\KnowledgeIngestionService;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeSource;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceId;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceType;
use App\Domain\Knowledge\ValueObjects\KnowledgeType;
use App\Domain\Tenant\ValueObjects\TenantId;

/**
 * Manual Postman flow: TEXT source → ingest → embed chunks.
 */
class IngestTextKnowledgeService
{
    public function __construct(
        private readonly KnowledgeIngestionService $ingestion,
        private readonly GenerateChunkEmbeddingService $embeddings,
        private readonly KnowledgeChunkRepositoryInterface $chunks,
    ) {}

    /**
     * @return array{source_id: string, document_id: string, chunks_count: int, vectors_created: int}
     */
    public function handle(string $tenantId, string $influencerId, string $title, string $content): array
    {
        $title = trim($title);
        $content = trim($content);
        if ($title === '' || $content === '') {
            throw new ApplicationException('Knowledge title and content are required.');
        }

        $source = KnowledgeSource::create(
            new KnowledgeSourceId(bin2hex(random_bytes(16))),
            new TenantId($tenantId),
            new InfluencerId($influencerId),
            new KnowledgeSourceType(KnowledgeSourceType::TEXT),
            $title,
            $content,
        );

        $result = $this->ingestion->ingest(
            $source,
            KnowledgeType::BACKGROUND,
            title: $title,
        );

        $document = $result->document;
        if ($document === null) {
            throw new ApplicationException('Knowledge ingestion did not produce a document.');
        }

        $chunks = $result->chunks;
        if ($chunks === [] && ! $result->duplicate) {
            throw new ApplicationException('Knowledge ingestion produced no chunks.');
        }

        if ($chunks === [] && $result->duplicate) {
            $chunks = $this->chunks->findByDocument(
                $document->tenantId,
                $document->influencerId,
                $document->id(),
            );
        }

        $vectorsCreated = 0;
        foreach ($chunks as $chunk) {
            $embedding = $this->embeddings->generate($chunk);
            if ($embedding->created) {
                $vectorsCreated++;
            }
        }

        return [
            'source_id' => (string) $source->id(),
            'document_id' => (string) $document->id(),
            'chunks_count' => count($chunks),
            'vectors_created' => $vectorsCreated,
        ];
    }
}
