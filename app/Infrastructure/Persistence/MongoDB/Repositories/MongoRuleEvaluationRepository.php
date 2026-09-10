<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\RuleRepositoryInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Rule\Entities\Rule;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\RuleDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\RuleMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;

final readonly class MongoRuleEvaluationRepository implements RuleRepositoryInterface
{
    public function __construct(private RuleMapper $mapper) {}

    public function findEnabledRules(TenantId $tenantId, InfluencerId $influencerId): array
    {
        return RuleDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('enabled', true)
            ->orderByDesc('priority')
            ->get()
            ->map(fn (RuleDocument $document) => $this->mapper->toDomain($document))
            ->all();
    }

    public function save(Rule $rule): void
    {
        ExplicitIdPersister::save(
            $this->mapper->toDocument($rule),
            (string) $rule->id(),
        );
    }
}
