<?php

namespace App\Domain\Rule\Services;

use App\Domain\Rule\Contracts\RuleMatcherInterface;
use App\Domain\Rule\Entities\Rule;
use App\Domain\Rule\ValueObjects\RuleType;

final class KeywordRuleMatcher implements RuleMatcherInterface
{
    public function supports(RuleType $type): bool
    {
        return $type->value === RuleType::KEYWORD;
    }

    public function match(Rule $rule, string $content): ?string
    {
        foreach ($rule->patterns as $pattern) {
            if (is_string($pattern) && $pattern !== '' && mb_stripos($content, $pattern) !== false) {
                return "Keyword [$pattern] matched.";
            }
        }

        return null;
    }
}
