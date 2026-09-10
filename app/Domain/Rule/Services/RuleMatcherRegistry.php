<?php

namespace App\Domain\Rule\Services;

use App\Domain\Rule\Contracts\RuleMatcherInterface;
use App\Domain\Rule\Entities\Rule;
use App\Domain\Shared\Exceptions\DomainException;

final readonly class RuleMatcherRegistry
{
    /** @param list<RuleMatcherInterface> $matchers */
    public function __construct(private array $matchers) {}

    public function match(Rule $rule, string $content): ?string
    {
        foreach ($this->matchers as $matcher) {
            if ($matcher->supports($rule->type)) {
                return $matcher->match($rule, $content);
            }
        }

        throw new DomainException("No matcher supports rule type [{$rule->type->value}].");
    }
}
