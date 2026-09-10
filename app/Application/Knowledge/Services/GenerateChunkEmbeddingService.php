<?php

namespace App\Application\Knowledge\Services;

use App\Application\AI\Contracts\EmbeddingProviderInterface;
use App\Application\Contracts\VectorRecordRepositoryInterface;
use App\Application\Exceptions\ApplicationException;
use App\Application\Knowledge\DTO\GenerateChunkEmbeddingResult;
use App\Domain\Knowledge\Entities\KnowledgeChunk;
use App\Domain\Knowledge\Entities\VectorRecord;
use App\Domain\Knowledge\ValueObjects\VectorRecordId;
use Throwable;

/**
 * Embeds a KnowledgeChunk and persists a VectorRecord with idempotency.
 */
final readonly class GenerateChunkEmbeddingService
{
    public function __construct(
        private EmbeddingProviderInterface $embeddings,
        private VectorRecordRepositoryInterface $vectors,
        private string $embeddingModel,
    ) {}

    public function generate(KnowledgeChunk $chunk): GenerateChunkEmbeddingResult
    {
        $contentHash = hash('sha256', $chunk->content);
        $existing = $this->vectors->findByIdempotencyKey(
            $chunk->tenantId,
            $chunk->influencerId,
            $chunk->id(),
            $this->embeddingModel,
            $contentHash,
        );

        if ($existing !== null) {
            return new GenerateChunkEmbeddingResult(
                $existing,
                false,
                true,
                'Vector already exists for chunk, model, and content hash.',
            );
        }

        try {
            $embedding = $this->embeddings->embed($chunk->content);
        } catch (ApplicationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new ApplicationException('Embedding generation failed.', 0, $exception);
        }

        $record = new VectorRecord(
            new VectorRecordId(bin2hex(random_bytes(16))),
            $chunk->tenantId,
            $chunk->influencerId,
            $chunk->id(),
            $embedding->vector,
            $embedding->dimensions,
            $this->embeddingModel,
            $contentHash,
            [
                'provider_model' => $embedding->model,
                'chunk_position' => $chunk->position,
                'document_version' => $chunk->documentVersion,
                ...$embedding->metadata,
            ],
        );

        $this->vectors->save($record);

        return new GenerateChunkEmbeddingResult(
            $record,
            true,
            false,
            'Vector record created.',
        );
    }
}
