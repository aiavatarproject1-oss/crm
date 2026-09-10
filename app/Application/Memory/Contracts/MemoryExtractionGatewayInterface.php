<?php

namespace App\Application\Memory\Contracts;

use App\Application\Memory\DTO\ExtractedMemoryCandidateData;
use App\Application\Memory\DTO\MemoryExtractionContext;

/**
 * Framework-independent AI / rule gateway for memory extraction.
 * Returns raw extracted data — never Memory aggregates.
 */
interface MemoryExtractionGatewayInterface
{
    /**
     * @return list<ExtractedMemoryCandidateData>
     */
    public function extract(MemoryExtractionContext $context): array;
}
