<?php

namespace App\Application\Knowledge\Parsers;

use App\Application\Exceptions\ApplicationException;
use App\Application\Knowledge\Contracts\KnowledgeParserInterface;
use App\Application\Knowledge\DTO\ParsedKnowledgeData;
use App\Domain\Knowledge\Entities\KnowledgeSource;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceType;

final readonly class PlainTextParser implements KnowledgeParserInterface
{
    public function supports(KnowledgeSource $source): bool
    {
        return $source->type->value === KnowledgeSourceType::TEXT;
    }

    public function parse(KnowledgeSource $source): ParsedKnowledgeData
    {
        if (! $this->supports($source)) {
            throw new ApplicationException('PlainTextParser only supports TEXT knowledge sources.');
        }

        $content = trim($source->payload);
        if ($content === '') {
            throw new ApplicationException('Knowledge source payload cannot be empty.');
        }

        return new ParsedKnowledgeData(
            $source->name,
            $content,
            [
                'parser' => 'plain_text',
                'source_type' => $source->type->value,
                ...$source->metadata,
            ],
        );
    }
}
