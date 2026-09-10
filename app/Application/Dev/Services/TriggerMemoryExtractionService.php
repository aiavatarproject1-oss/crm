<?php

namespace App\Application\Dev\Services;

use App\Application\Contracts\MessageBatchRepositoryInterface;
use App\Application\Exceptions\ApplicationException;
use App\Application\Memory\Contracts\MemoryExtractorInterface;
use App\Application\Memory\Services\MemoryConsolidationService;
use App\Application\Memory\Services\MemoryEvaluator;
use App\Domain\Message\ValueObjects\MessageBatchId;

/**
 * Manual Postman trigger: MessageBatch → extract → evaluate → consolidate.
 */
class TriggerMemoryExtractionService
{
    public function __construct(
        private readonly MessageBatchRepositoryInterface $batches,
        private readonly MemoryExtractorInterface $extractor,
        private readonly MemoryEvaluator $evaluator,
        private readonly MemoryConsolidationService $consolidation,
    ) {}

    /**
     * @return array{candidates_created: int, memories_created: int}
     */
    public function handle(string $messageBatchId): array
    {
        $batch = $this->batches->find(new MessageBatchId($messageBatchId));
        if ($batch === null) {
            throw new ApplicationException('Message batch was not found.');
        }

        $candidates = $this->extractor->extract($batch);
        $memoriesCreated = 0;

        foreach ($candidates as $candidate) {
            $evaluation = $this->evaluator->evaluate($candidate);
            if (! $evaluation->approved()) {
                continue;
            }

            $decision = $this->consolidation->consolidate($candidate);
            if ($decision->resultingMemory !== null) {
                $memoriesCreated++;
            }
        }

        return [
            'candidates_created' => count($candidates),
            'memories_created' => $memoriesCreated,
        ];
    }
}
