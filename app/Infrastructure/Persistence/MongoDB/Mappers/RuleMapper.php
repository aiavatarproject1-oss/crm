<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Rule\Entities\Rule;
use App\Domain\Rule\ValueObjects\RuleDecision;
use App\Domain\Rule\ValueObjects\RuleId;
use App\Domain\Rule\ValueObjects\RuleType;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\RuleDocument;

final class RuleMapper
{
    public function toDocument(Rule $rule): RuleDocument
    {
        $document = new RuleDocument([
            'tenant_id' => (string) $rule->tenantId,
            'influencer_id' => (string) $rule->influencerId,
            'name' => $rule->name,
            'type' => $rule->type->value,
            'patterns' => $rule->patterns,
            'priority' => $rule->priority,
            'action' => $rule->action->value,
            'enabled' => $rule->enabled,
            'version' => $rule->version,
            'metadata' => $rule->metadata,
        ]);
        $document->setAttribute('_id', (string) $rule->id());

        return $document;
    }

    public function toDomain(RuleDocument $document): Rule
    {
        return new Rule(
            new RuleId((string) $document->getAttribute('_id')),
            new TenantId((string) $document->tenant_id),
            new InfluencerId((string) $document->influencer_id),
            (string) $document->name,
            new RuleType((string) $document->type),
            (array) $document->patterns,
            (int) $document->priority,
            new RuleDecision((string) $document->action),
            (bool) $document->enabled,
            (int) $document->version,
            (array) ($document->metadata ?? []),
        );
    }
}
