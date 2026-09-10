<?php

namespace App\Application\Memory\Contracts;

use App\Domain\Memory\Entities\MemoryCandidate;
use App\Domain\Message\Entities\MessageBatch;

/**
 * High-level extraction entry: MessageBatch → MemoryCandidate list.
 */
interface MemoryExtractorInterface
{
    /**
     * @return list<MemoryCandidate>
     */
    public function extract(MessageBatch $batch): array;
}
