<?php

namespace App\Domain\Rule\Entities;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Rule\ValueObjects\RuleDecision;
use App\Domain\Rule\ValueObjects\RuleId;
use App\Domain\Rule\ValueObjects\RuleType;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Tenant\ValueObjects\TenantId;

final readonly class Rule implements Entity
{
    public function __construct(
        private RuleId $ruleId,
        public TenantId $tenantId,
        public InfluencerId $influencerId,
        public string $name,
        public RuleType $type,
        public array $patterns,
        public int $priority,
        public RuleDecision $action,
        public bool $enabled,
        public int $version,
        public array $metadata = [],
    ) {}

    public function id(): RuleId
    {
        return $this->ruleId;
    }
}
