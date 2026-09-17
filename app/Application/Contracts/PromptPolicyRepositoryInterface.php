<?php

namespace App\Application\Contracts;

use App\Application\AI\DTO\PromptPolicyData;

interface PromptPolicyRepositoryInterface
{
    public function findGlobal(): ?PromptPolicyData;

    public function findForTenant(string $tenantId): ?PromptPolicyData;

    public function save(PromptPolicyData $policy): void;
}
