<?php

namespace App\Application\Memory\Services;

use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\Memory\Contracts\MemoryExtractorInterface;
use App\Application\Memory\DTO\MemoryExtractionContext;
use App\Domain\Message\Entities\MessageBatch;

/**
 * High-level extractor: MessageBatch → MemoryCandidate[] via MemoryExtractionService.
 */
final readonly class MessageBatchMemoryExtractor implements MemoryExtractorInterface
{
    public function __construct(
        private MessageRepositoryInterface $messages,
        private MemoryExtractionService $extraction,
    ) {}

    public function extract(MessageBatch $batch): array
    {
        $recent = $this->messages->findRecentByConversation(
            $batch->tenantId,
            $batch->influencerId,
            $batch->conversationId,
            max($batch->messageCount, 1),
        );

        $context = MemoryExtractionContext::fromBatch($batch, $recent);

        return $this->extraction->extract($context);
    }
}
