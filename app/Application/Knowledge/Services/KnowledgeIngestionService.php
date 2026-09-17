<?php

namespace App\Application\Knowledge\Services;

use App\Application\Contracts\KnowledgeChunkRepositoryInterface;
use App\Application\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Contracts\KnowledgeSourceRepositoryInterface;
use App\Application\Exceptions\ApplicationException;
use App\Application\Knowledge\Contracts\ChunkingStrategyInterface;
use App\Application\Knowledge\Contracts\KnowledgeParserInterface;
use App\Application\Knowledge\DTO\KnowledgeIngestionResult;
use App\Domain\Knowledge\Entities\KnowledgeChunk;
use App\Domain\Knowledge\Entities\KnowledgeDocument;
use App\Domain\Knowledge\Entities\KnowledgeSource;
use App\Domain\Knowledge\ValueObjects\KnowledgeChunkId;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentStatus;
use App\Domain\Knowledge\ValueObjects\KnowledgeType;
use Throwable;

/**
 * Source → parse → chunk → KnowledgeDocument + KnowledgeChunk.
 * Does not create embeddings or vectors.
 */
final readonly class KnowledgeIngestionService
{
    public function __construct(
        private KnowledgeParserInterface $parser,
        private ChunkingStrategyInterface $chunker,
        private KnowledgeSourceRepositoryInterface $sources,
        private KnowledgeDocumentRepositoryInterface $documents,
        private KnowledgeChunkRepositoryInterface $chunks,
    ) {}

    public function ingest(
        KnowledgeSource $source,
        KnowledgeType $type,
        ?KnowledgeDocumentId $documentId = null,
        ?string $title = null,
    ): KnowledgeIngestionResult {
        $this->sources->save($source);

        $existingByChecksum = $this->documents->findByChecksum(
            $source->tenantId,
            $source->influencerId,
            $source->checksum,
        );
        if ($existingByChecksum !== null) {
            return new KnowledgeIngestionResult(
                $source,
                $existingByChecksum,
                [],
                false,
                true,
                'Duplicate checksum — knowledge document already ingested.',
            );
        }

        try {
            if (! $this->parser->supports($source)) {
                throw new ApplicationException('No parser supports this knowledge source type.');
            }
            $parsed = $this->parser->parse($source);
            $source->markParsed();
            $this->sources->save($source);

            $chunkData = $this->chunker->chunk($parsed);
            $documentId ??= new KnowledgeDocumentId(bin2hex(random_bytes(16)));
            $latest = $this->documents->findLatestVersion($source->tenantId, $source->influencerId, $documentId);
            $version = $latest === null ? 1 : $latest->version + 1;

            $document = new KnowledgeDocument(
                $documentId,
                $source->tenantId,
                $source->influencerId,
                $type,
                $title ?? $parsed->title,
                $parsed->content,
                $version,
                KnowledgeDocumentStatus::active(),
                $source->type->value,
                [
                    'ingestion' => [
                        'parser' => $parsed->metadata['parser'] ?? null,
                        'chunk_count' => count($chunkData),
                    ],
                    ...$parsed->metadata,
                ],
                $source->id(),
                $source->checksum,
            );
            $this->documents->save($document);

            $chunks = [];
            foreach ($chunkData as $item) {
                $chunk = new KnowledgeChunk(
                    new KnowledgeChunkId(bin2hex(random_bytes(16))),
                    $source->tenantId,
                    $source->influencerId,
                    $documentId,
                    $item->content,
                    $item->position,
                    $item->tokenCount,
                    [
                        'source_id' => (string) $source->id(),
                        ...$item->metadata,
                    ],
                    $version,
                );
                $this->chunks->save($chunk);
                $chunks[] = $chunk;
            }

            $source->markIngested();
            $this->sources->save($source);

            return new KnowledgeIngestionResult($source, $document, $chunks, true, false, 'Knowledge ingested.');
        } catch (Throwable $exception) {
            dd($exception);
            $source->markFailed($exception->getMessage());
            $this->sources->save($source);

            if ($exception instanceof ApplicationException) {
                throw $exception;
            }

            throw new ApplicationException('Knowledge ingestion failed.', 0, $exception);
        }
    }
}
