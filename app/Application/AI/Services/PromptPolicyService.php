<?php

namespace App\Application\AI\Services;

use App\Application\AI\DTO\PromptPolicyData;
use App\Application\Contracts\PromptPolicyRepositoryInterface;

final readonly class PromptPolicyService
{
    public function __construct(private PromptPolicyRepositoryInterface $policies) {}

    public function resolve(?string $tenantId = null): PromptPolicyData
    {
        if ($tenantId !== null && trim($tenantId) !== '') {
            $tenantPolicy = $this->policies->findForTenant(trim($tenantId));
            if ($tenantPolicy !== null) {
                return $tenantPolicy;
            }
        }

        return $this->policies->findGlobal() ?? PromptPolicyData::defaults();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertGlobal(array $payload): PromptPolicyData
    {
        $policy = PromptPolicyData::fromArray([
            ...$payload,
            'id' => 'global',
            'tenant_id' => null,
        ], 'global');
        $this->policies->save($policy);

        return $policy;
    }
}
