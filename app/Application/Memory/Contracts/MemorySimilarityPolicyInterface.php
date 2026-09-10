<?php

namespace App\Application\Memory\Contracts;

use App\Application\Memory\DTO\MemoryRelation;

/**
 * Deterministic similarity decisions for duplicate vs conflict (no embeddings).
 */
interface MemorySimilarityPolicyInterface
{
    public function compare(string $left, string $right): MemoryRelation;
}
