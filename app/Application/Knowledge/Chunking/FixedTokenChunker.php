<?php

namespace App\Application\Knowledge\Chunking;

use App\Application\Knowledge\Contracts\ChunkingStrategyInterface;
use App\Application\Knowledge\DTO\KnowledgeChunkData;
use App\Application\Knowledge\DTO\ParsedKnowledgeData;

/**
 * Deterministic whitespace-token chunker. Replaceable via ChunkingStrategyInterface.
 */
final readonly class FixedTokenChunker implements ChunkingStrategyInterface
{
    public function __construct(
        private int $chunkSize = 50,
        private int $overlap = 10,
    ) {
        if ($this->chunkSize < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1.');
        }
        if ($this->overlap < 0 || $this->overlap >= $this->chunkSize) {
            throw new \InvalidArgumentException('Overlap must be >= 0 and < chunk size.');
        }
    }

    public function chunk(ParsedKnowledgeData $parsed): array
    {
        $tokens = preg_split('/\s+/u', trim($parsed->content), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return [];
        }

        $chunks = [];
        $position = 0;
        $offset = 0;
        $total = count($tokens);

        while ($offset < $total) {
            $slice = array_slice($tokens, $offset, $this->chunkSize);
            $content = implode(' ', $slice);
            $chunks[] = new KnowledgeChunkData(
                $content,
                $position,
                count($slice),
                ['chunker' => 'fixed_token', 'offset' => $offset],
            );
            $position++;
            $next = $offset + $this->chunkSize - $this->overlap;
            if ($next <= $offset) {
                break;
            }
            $offset = $next;
            if ($offset >= $total) {
                break;
            }
        }

        return $chunks;
    }
}
