<?php

namespace App\Domain\Rule\Contracts;

use App\Domain\Rule\Entities\Rule;
use App\Domain\Rule\ValueObjects\RuleType;

interface RuleMatcherInterface
{
    public function supports(RuleType $type): bool;

    public function match(Rule $rule, string $content): ?string;
}
