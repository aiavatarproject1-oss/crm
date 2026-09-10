<?php

namespace App\Application\Memory\Policies;

use App\Application\Memory\Contracts\MemoryConflictDetectorInterface;
use App\Application\Memory\Contracts\MemorySimilarityPolicyInterface;
use App\Application\Memory\DTO\MemoryConflictAssessment;
use App\Application\Memory\DTO\MemoryRelation;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Memory\Entities\MemoryCandidate;

/**
 * Compares a candidate against ACTIVE same-type memories in the provided list.
 */
final readonly class ScopedMemoryConflictDetector implements MemoryConflictDetectorInterface
{
    public function __construct(private MemorySimilarityPolicyInterface $similarity) {}

    public function detect(MemoryCandidate $candidate, array $existing): MemoryConflictAssessment
    {
        $bestDuplicate = null;
        $bestConflict = null;

        foreach ($existing as $memory) {
            if (! $memory instanceof Memory) {
                continue;
            }
            if (! $memory->status()->isActive()) {
                continue;
            }
            if (! $memory->belongsToScope($candidate->tenantId, $candidate->influencerId, $candidate->userId)) {
                continue;
            }
            if ($memory->type->value !== $candidate->type->value) {
                continue;
            }

            $relation = $this->similarity->compare($candidate->content, $memory->content);

            if ($relation->isDuplicate()) {
                if ($bestDuplicate === null || $relation->score > $bestDuplicate['relation']->score) {
                    $bestDuplicate = ['memory' => $memory, 'relation' => $relation];
                }
            } elseif ($relation->isConflict()) {
                if ($bestConflict === null || $relation->score > $bestConflict['relation']->score) {
                    $bestConflict = ['memory' => $memory, 'relation' => $relation];
                }
            }
        }

        if ($bestDuplicate !== null) {
            return new MemoryConflictAssessment(
                $bestDuplicate['relation'],
                $bestDuplicate['memory'],
                'Candidate duplicates an existing active memory.',
                ['score' => $bestDuplicate['relation']->score, ...$bestDuplicate['relation']->metadata],
            );
        }

        if ($bestConflict !== null) {
            return new MemoryConflictAssessment(
                $bestConflict['relation'],
                $bestConflict['memory'],
                'Candidate conflicts with an existing active memory.',
                ['score' => $bestConflict['relation']->score, ...$bestConflict['relation']->metadata],
            );
        }

        return new MemoryConflictAssessment(
            new MemoryRelation(MemoryRelation::INDEPENDENT, 0.0),
            null,
            'Candidate is independent of existing active memories.',
        );
    }
}
