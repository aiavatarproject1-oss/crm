<?php

namespace App\Application\AI\Contracts;

use App\Application\AI\DTO\EmbeddingResult;

interface EmbeddingProviderInterface
{
    public function embed(string $text): EmbeddingResult;
}
