<?php

namespace App\Interfaces\Http\Controllers\Dev;

use App\Application\Contracts\RuleRepositoryInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Rule\Entities\Rule;
use App\Domain\Rule\ValueObjects\RuleDecision;
use App\Domain\Rule\ValueObjects\RuleId;
use App\Domain\Rule\ValueObjects\RuleType;
use App\Domain\Tenant\ValueObjects\TenantId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule as ValidationRule;

final readonly class DevRuleController
{
    public function __construct(private RuleRepositoryInterface $rules) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string'],
            'influencer_id' => ['required', 'string'],
            'type' => ['required', 'string', ValidationRule::in([RuleType::KEYWORD, RuleType::REGEX])],
            'pattern' => ['required', 'string'],
            'decision' => ['required', 'string', ValidationRule::in([
                RuleDecision::ALLOW_AI,
                RuleDecision::ADMIN_REVIEW,
                RuleDecision::BLOCK,
                RuleDecision::IGNORE,
            ])],
            'priority' => ['required', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $rule = new Rule(
            new RuleId(bin2hex(random_bytes(16))),
            new TenantId((string) $validated['tenant_id']),
            new InfluencerId((string) $validated['influencer_id']),
            (string) ($validated['name'] ?? ('rule-'.$validated['type'])),
            new RuleType((string) $validated['type']),
            [(string) $validated['pattern']],
            (int) $validated['priority'],
            new RuleDecision((string) $validated['decision']),
            true,
            1,
        );
        $this->rules->save($rule);

        return response()->json([
            'success' => true,
            'data' => $this->toArray($rule),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string'],
            'influencer_id' => ['required', 'string'],
        ]);

        $rules = $this->rules->findEnabledRules(
            new TenantId((string) $validated['tenant_id']),
            new InfluencerId((string) $validated['influencer_id']),
        );

        return response()->json([
            'success' => true,
            'data' => array_map(fn (Rule $rule): array => $this->toArray($rule), $rules),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Rule $rule): array
    {
        return [
            'id' => (string) $rule->id(),
            'tenant_id' => (string) $rule->tenantId,
            'influencer_id' => (string) $rule->influencerId,
            'name' => $rule->name,
            'type' => $rule->type->value,
            'patterns' => $rule->patterns,
            'decision' => $rule->action->value,
            'priority' => $rule->priority,
            'enabled' => $rule->enabled,
            'version' => $rule->version,
        ];
    }
}
