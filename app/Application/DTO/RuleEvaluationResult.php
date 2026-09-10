<?php

namespace App\Application\DTO;

use App\Domain\Rule\ValueObjects\RuleDecision;

final readonly class RuleEvaluationResult
{
    public function __construct(
        public RuleDecision $decision,
        public string $reason,
        public ?array $matched_rule,
    ) {}
}
