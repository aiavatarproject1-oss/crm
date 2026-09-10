<?php

namespace App\Application\Knowledge\Contracts;

use App\Application\Knowledge\DTO\KnowledgeChunkData;
use App\Application\Knowledge\DTO\ParsedKnowledgeData;

interface ChunkingStrategyInterface
{
    /**
     * @return list<KnowledgeChunkData>
     */
    public function chunk(ParsedKnowledgeData $parsed): array;
}
