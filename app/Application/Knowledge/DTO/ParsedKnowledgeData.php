<?php

namespace App\Application\Knowledge\DTO;

final readonly class ParsedKnowledgeData
{
    public function __construct(
        public string $title,
        public string $content,
        public array $metadata = [],
    ) {}
}
