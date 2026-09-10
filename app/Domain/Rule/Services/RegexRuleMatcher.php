<?php

namespace App\Domain\Rule\Services;

use App\Domain\Rule\Contracts\RuleMatcherInterface;
use App\Domain\Rule\Entities\Rule;
use App\Domain\Rule\ValueObjects\RuleType;

final class RegexRuleMatcher implements RuleMatcherInterface
{
    public function supports(RuleType $type): bool
    {
        return $type->value === RuleType::REGEX;
    }

    public function match(Rule $rule, string $content): ?string
    {
        foreach ($rule->patterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && @preg_match($pattern, $content) === 1) {
                return "Regular expression [$pattern] matched.";
            }
        }

        return null;
    }
}
