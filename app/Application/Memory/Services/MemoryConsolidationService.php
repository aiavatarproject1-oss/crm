<?php

namespace App\Application\Memory\Services;

use App\Application\Contracts\MemoryRepositoryInterface;
use App\Application\Memory\Contracts\MemoryConflictDetectorInterface;
use App\Application\Memory\DTO\MemoryConsolidationDecision;
use App\Application\Memory\DTO\MemoryRelation;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Memory\Entities\MemoryCandidate;
use App\Domain\Memory\ValueObjects\MemoryId;

/**
 * Resolves an approved/pending candidate against existing ACTIVE memories.
 * Never deletes memories — conflicts supersede with history metadata.
 */
final readonly class MemoryConsolidationService
{
    public function __construct(
        private MemoryRepositoryInterface $memories,
        private MemoryConflictDetectorInterface $conflicts,
    ) {}

    public function consolidate(MemoryCandidate $candidate): MemoryConsolidationDecision
    {
        $existing = $this->memories->findActiveForUser(
            $candidate->tenantId,
            $candidate->influencerId,
            $candidate->userId,
            100,
        );

        $assessment = $this->conflicts->detect($candidate, $existing);

        return match ($assessment->relation->value) {
            MemoryRelation::DUPLICATE => $this->rejectDuplicate($candidate, $assessment->relatedMemory, $assessment->reason, $assessment->metadata),
            MemoryRelation::CONFLICT => $this->supersedeExisting($candidate, $assessment->relatedMemory, $assessment->reason, $assessment->metadata),
            default => $this->createNew($candidate, $assessment->reason, $assessment->metadata),
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function rejectDuplicate(
        MemoryCandidate $candidate,
        ?Memory $existing,
        string $reason,
        array $metadata,
    ): MemoryConsolidationDecision {
        if ($candidate->status()->isPending()) {
            $candidate->reject('Rejected as duplicate during consolidation.');
        }

        return new MemoryConsolidationDecision(
            MemoryConsolidationDecision::REJECT_DUPLICATE,
            $reason,
            $existing,
            null,
            [
                'candidate_id' => (string) $candidate->id(),
                'relation' => MemoryRelation::DUPLICATE,
                ...$metadata,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createNew(
        MemoryCandidate $candidate,
        string $reason,
        array $metadata,
    ): MemoryConsolidationDecision {
        $memory = $this->memoryFromCandidate($candidate, [
            'consolidation' => [
                'strategy' => MemoryConsolidationDecision::CREATE_NEW,
                'reason' => $reason,
            ],
        ]);
        $this->memories->save($memory);

        return new MemoryConsolidationDecision(
            MemoryConsolidationDecision::CREATE_NEW,
            $reason,
            null,
            $memory,
            [
                'candidate_id' => (string) $candidate->id(),
                'relation' => MemoryRelation::INDEPENDENT,
                'target_memory_id' => (string) $memory->id(),
                ...$metadata,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function supersedeExisting(
        MemoryCandidate $candidate,
        ?Memory $existing,
        string $reason,
        array $metadata,
    ): MemoryConsolidationDecision {
        if ($existing === null) {
            return $this->createNew($candidate, 'Conflict reported without related memory; creating new.', $metadata);
        }

        $replacement = $this->memoryFromCandidate($candidate, [
            'consolidation' => [
                'strategy' => MemoryConsolidationDecision::SUPERSEDE_EXISTING,
                'reason' => $reason,
                'source_memory_id' => (string) $existing->id(),
                'target_memory_id' => null,
            ],
        ]);

        // Bind bidirectional history before save.
        $replacement->metadata['consolidation']['target_memory_id'] = (string) $replacement->id();
        $replacement->metadata['consolidation']['supersedes'] = (string) $existing->id();

        $this->memories->save($replacement);

        $existing->supersede($replacement->id());
        $existing->metadata['consolidation'] = [
            'strategy' => MemoryConsolidationDecision::SUPERSEDE_EXISTING,
            'reason' => $reason,
            'source_memory_id' => (string) $existing->id(),
            'target_memory_id' => (string) $replacement->id(),
            'relation' => MemoryRelation::CONFLICT,
            ...$metadata,
        ];
        $this->memories->save($existing);

        return new MemoryConsolidationDecision(
            MemoryConsolidationDecision::SUPERSEDE_EXISTING,
            $reason,
            $existing,
            $replacement,
            [
                'candidate_id' => (string) $candidate->id(),
                'relation' => MemoryRelation::CONFLICT,
                'source_memory_id' => (string) $existing->id(),
                'target_memory_id' => (string) $replacement->id(),
                ...$metadata,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $extraMetadata
     */
    private function memoryFromCandidate(MemoryCandidate $candidate, array $extraMetadata = []): Memory
    {
        return Memory::create(
            new MemoryId(bin2hex(random_bytes(16))),
            $candidate->tenantId,
            $candidate->influencerId,
            $candidate->userId,
            $candidate->type,
            $candidate->content,
            $candidate->confidenceScore,
            $candidate->importanceScore,
            $candidate->sourceMessageBatchId,
            [
                'source_candidate_id' => (string) $candidate->id(),
                ...$candidate->metadata,
                ...$extraMetadata,
            ],
        );
    }
}
