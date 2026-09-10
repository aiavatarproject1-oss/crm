<?php

namespace App\Application\RAG\DTO;

use App\Domain\Knowledge\Entities\VectorRecord;

final readonly class VectorSearchResult
{
    public function __construct(public VectorRecord $record, public float $score) {}
}
