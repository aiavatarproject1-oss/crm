<?php

namespace App\Application\Memory\Services;

use App\Application\Memory\Contracts\MemoryEvaluationPolicyInterface;
use App\Application\Memory\DTO\MemoryEvaluationResult;
use App\Domain\Memory\Entities\MemoryCandidate;

/**
 * Applies evaluation policy and transitions candidate status. Does not create Memory aggregates.
 */
final readonly class MemoryEvaluator
{
    public function __construct(private MemoryEvaluationPolicyInterface $policy) {}

    public function evaluate(MemoryCandidate $candidate): MemoryEvaluationResult
    {
        $result = $this->policy->evaluate($candidate);

        if ($result->approved()) {
            $candidate->approve();
        } else {
            $candidate->reject($result->reason);
        }

        return $result;
    }
}
