<?php

namespace App\Services\Rules;

use App\Repositories\Contracts\RuleRepositoryInterface;

class RuleService
{
    public function __construct(
        protected RuleRepositoryInterface $rules,
    ) {}
}
