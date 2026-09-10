<?php

namespace App\Application\Knowledge\Contracts;

use App\Application\Knowledge\DTO\ParsedKnowledgeData;
use App\Domain\Knowledge\Entities\KnowledgeSource;

interface KnowledgeParserInterface
{
    public function supports(KnowledgeSource $source): bool;

    public function parse(KnowledgeSource $source): ParsedKnowledgeData;
}
