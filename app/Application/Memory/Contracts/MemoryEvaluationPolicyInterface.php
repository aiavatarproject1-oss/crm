<?php

namespace App\Application\Memory\Contracts;

use App\Application\Memory\DTO\MemoryEvaluationResult;
use App\Domain\Memory\Entities\MemoryCandidate;

/**
 * Decides APPROVE vs REJECT for a MemoryCandidate without creating Memory rows.
 */
interface MemoryEvaluationPolicyInterface
{
    public function evaluate(MemoryCandidate $candidate): MemoryEvaluationResult;
}
