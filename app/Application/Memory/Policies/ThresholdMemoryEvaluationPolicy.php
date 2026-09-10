<?php

namespace App\Application\Memory\Policies;

use App\Application\Memory\Contracts\MemoryDuplicateDetectorInterface;
use App\Application\Memory\Contracts\MemoryEvaluationPolicyInterface;
use App\Application\Memory\DTO\MemoryEvaluationResult;
use App\Domain\Memory\Entities\MemoryCandidate;

/**
 * Deterministic gate: thresholds, empty content, importance bounds, duplicate contract.
 */
final readonly class ThresholdMemoryEvaluationPolicy implements MemoryEvaluationPolicyInterface
{
    public function __construct(
        private MemoryDuplicateDetectorInterface $duplicates,
        private float $minConfidence = 0.6,
        private float $minImportance = 0.1,
    ) {}

    public function evaluate(MemoryCandidate $candidate): MemoryEvaluationResult
    {
        $issues = [];

        if (trim($candidate->content) === '') {
            $issues[] = 'empty_content';
        }

        if ($candidate->confidenceScore < $this->minConfidence) {
            $issues[] = 'low_confidence';
        }

        if ($candidate->importanceScore < $this->minImportance || $candidate->importanceScore > 1.0) {
            $issues[] = 'invalid_importance';
        }

        if ($this->duplicates->isDuplicate($candidate)) {
            $issues[] = 'duplicate';
        }

        if ($issues !== []) {
            return new MemoryEvaluationResult(
                MemoryEvaluationResult::REJECT,
                'Memory candidate rejected by evaluation policy.',
                $issues,
                [
                    'min_confidence' => $this->minConfidence,
                    'min_importance' => $this->minImportance,
                    'confidence_score' => $candidate->confidenceScore,
                    'importance_score' => $candidate->importanceScore,
                ],
            );
        }

        return new MemoryEvaluationResult(
            MemoryEvaluationResult::APPROVE,
            'Memory candidate approved by evaluation policy.',
            [],
            [
                'min_confidence' => $this->minConfidence,
                'min_importance' => $this->minImportance,
                'confidence_score' => $candidate->confidenceScore,
                'importance_score' => $candidate->importanceScore,
            ],
        );
    }
}
