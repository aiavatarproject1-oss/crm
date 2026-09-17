<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\AI\DTO\PromptPolicyData;
use App\Application\Contracts\PromptPolicyRepositoryInterface;
use App\Infrastructure\Persistence\MongoDB\Documents\PromptPolicyDocument;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;

final readonly class MongoPromptPolicyRepository implements PromptPolicyRepositoryInterface
{
    public function findGlobal(): ?PromptPolicyData
    {
        $document = PromptPolicyDocument::query()->where('_id', 'global')->first()
            ?? PromptPolicyDocument::query()->whereNull('tenant_id')->where('id', 'global')->first();

        return $document === null ? null : $this->toData($document);
    }

    public function findForTenant(string $tenantId): ?PromptPolicyData
    {
        $document = PromptPolicyDocument::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('updated_at')
            ->first();

        return $document === null ? null : $this->toData($document);
    }

    public function save(PromptPolicyData $policy): void
    {
        $id = $policy->tenantId === null ? 'global' : 'tenant-'.$policy->tenantId;
        $payload = $policy->toArray();
        $payload['id'] = $id;

        ExplicitIdPersister::save(
            (new PromptPolicyDocument)->forceFill($payload),
            $id,
        );
    }

    private function toData(PromptPolicyDocument $document): PromptPolicyData
    {
        return PromptPolicyData::fromArray([
            'id' => (string) ($document->getAttribute('id') ?? $document->getKey() ?? 'global'),
            'tenant_id' => $document->getAttribute('tenant_id'),
            'role_intro' => $document->getAttribute('role_intro'),
            'style_lines' => $document->getAttribute('style_lines') ?? [],
            'stage_lines' => $document->getAttribute('stage_lines') ?? [],
            'closing_line' => $document->getAttribute('closing_line'),
        ]);
    }
}
