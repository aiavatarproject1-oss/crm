<?php

namespace App\Application\AI\DTO;

use InvalidArgumentException;

final readonly class EmbeddingResult
{
    /**
     * @param  list<float|int>  $vector
     */
    public function __construct(
        public array $vector,
        public string $model,
        public int $dimensions,
        public array $metadata = [],
    ) {
        if ($dimensions < 1 || count($vector) !== $dimensions) {
            throw new InvalidArgumentException('Embedding dimensions must match the vector size.');
        }

        foreach ($vector as $value) {
            if (! is_int($value) && ! is_float($value)) {
                throw new InvalidArgumentException('Embedding vectors may only contain numeric values.');
            }
        }
    }
}
