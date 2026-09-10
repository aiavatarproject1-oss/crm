<?php

namespace App\Application\Dev\Services;

use App\Application\Contracts\PersonaRepositoryInterface;
use App\Domain\Influencer\Entities\Persona;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Influencer\ValueObjects\PersonaId;
use App\Domain\Tenant\ValueObjects\TenantId;

/**
 * Development setup helpers for Postman (opaque tenant/influencer IDs + Persona).
 */
final readonly class DevSetupService
{
    public function __construct(private PersonaRepositoryInterface $personas) {}

    /**
     * @return array{tenant_id: string, name: string}
     */
    public function createTenant(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            $name = 'Dev Tenant';
        }

        return [
            'tenant_id' => bin2hex(random_bytes(16)),
            'name' => $name,
        ];
    }

    /**
     * @param  array<string, mixed>  $persona
     * @return array{influencer_id: string, tenant_id: string, persona_id: string}
     */
    public function createInfluencer(string $tenantId, string $name, array $persona = []): array
    {
        $name = trim($name);
        if ($name === '') {
            $name = 'Dev Influencer';
        }

        $influencerId = new InfluencerId(bin2hex(random_bytes(16)));
        $tenant = new TenantId($tenantId);
        $personaId = new PersonaId(bin2hex(random_bytes(16)));

        $this->personas->save(new Persona(
            $personaId,
            $tenant,
            $influencerId,
            $name,
            (string) ($persona['language'] ?? 'en'),
            (string) ($persona['tone'] ?? 'warm'),
            (string) ($persona['style'] ?? 'friendly'),
            (string) ($persona['description'] ?? ($persona['persona'] ?? 'A helpful AI influencer.')),
            array_values((array) ($persona['system_rules'] ?? ['Be respectful'])),
            [
                'persona' => $persona['persona'] ?? ($persona['description'] ?? null),
                ...$persona,
            ],
        ));

        return [
            'influencer_id' => (string) $influencerId,
            'tenant_id' => $tenantId,
            'persona_id' => (string) $personaId,
        ];
    }
}
