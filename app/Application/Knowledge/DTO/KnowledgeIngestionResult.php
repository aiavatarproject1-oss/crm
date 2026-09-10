<?php

namespace App\Application\Knowledge\DTO;

use App\Domain\Knowledge\Entities\KnowledgeChunk;
use App\Domain\Knowledge\Entities\KnowledgeDocument;
use App\Domain\Knowledge\Entities\KnowledgeSource;

final readonly class KnowledgeIngestionResult
{
    /**
     * @param  list<KnowledgeChunk>  $chunks
     */
    public function __construct(
        public KnowledgeSource $source,
        public ?KnowledgeDocument $document,
        public array $chunks,
        public bool $created,
        public bool $duplicate,
        public string $reason = '',
    ) {}
}
