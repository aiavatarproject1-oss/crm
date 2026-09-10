<?php

namespace App\Application\Memory\Contracts;

use App\Domain\Memory\Entities\MemoryCandidate;

/**
 * Detects whether a candidate duplicates an existing scoped Memory.
 */
interface MemoryDuplicateDetectorInterface
{
    public function isDuplicate(MemoryCandidate $candidate): bool;
}
