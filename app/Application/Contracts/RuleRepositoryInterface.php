<?php

namespace App\Application\Contracts;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Rule\Entities\Rule;
use App\Domain\Shared\Contracts\RepositoryInterface;
use App\Domain\Tenant\ValueObjects\TenantId;

interface RuleRepositoryInterface extends RepositoryInterface
{
    /** @return list<Rule> */
    public function findEnabledRules(TenantId $tenantId, InfluencerId $influencerId): array;

    public function save(Rule $rule): void;
}
