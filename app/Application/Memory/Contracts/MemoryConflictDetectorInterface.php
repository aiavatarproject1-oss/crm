<?php

namespace App\Application\Memory\Contracts;

use App\Application\Memory\DTO\MemoryConflictAssessment;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Memory\Entities\MemoryCandidate;

interface MemoryConflictDetectorInterface
{
    /**
     * @param  list<Memory>  $existing
     */
    public function detect(MemoryCandidate $candidate, array $existing): MemoryConflictAssessment;
}
