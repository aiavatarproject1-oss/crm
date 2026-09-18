<?php

namespace App\Application\Dev\Services;

use App\Application\Character\Contracts\CharacterSettingsRepositoryInterface;
use App\Application\Character\DTO\CharacterSettingsData;
use App\Application\Contracts\PersonaRepositoryInterface;
use App\Domain\Influencer\Entities\Persona;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Influencer\ValueObjects\PersonaId;
use App\Domain\Tenant\ValueObjects\TenantId;
use RuntimeException;

/**
 * Development helpers: tenants + bind runtime Persona to an admin AI Character.
 */
final readonly class DevSetupService
{
    public function __construct(
        private PersonaRepositoryInterface $personas,
        private CharacterSettingsRepositoryInterface $characters,
    ) {}

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
     * @return list<array<string, mixed>>
     */
    public function listCharacters(): array
    {
        return array_map(
            static fn (CharacterSettingsData $c): array => [
                'id' => $c->id,
                'tenant_id' => $c->tenantId,
                'character_id' => $c->characterId,
                'slug' => $c->slug,
                'display_name' => $c->displayName,
                'status' => $c->status,
                'version' => $c->version,
            ],
            $this->characters->all(),
        );
    }

    /**
     * Ensure a runtime Persona exists for chat pipeline, synced from admin Character settings.
     * Pipeline still keys scope as influencer_id internally (= character_id).
     *
     * @return array{character_id: string, tenant_id: string, persona_id: string, display_name: string}
     */
    public function ensurePersonaForCharacter(string $characterId): array
    {
        $settings = $this->characters->find($characterId)
            ?? $this->characters->findBySlug($characterId);

        if ($settings === null) {
            throw new RuntimeException('AI Character not found. Create/edit it in the admin panel first.');
        }

        $tenant = new TenantId($settings->tenantId);
        $character = new InfluencerId($settings->characterId);
        $existing = $this->personas->findByInfluencer($tenant, $character);

        $identity = $settings->identity;
        $behavior = $settings->behavior;
        $name = (string) ($identity['name'] ?? $settings->displayName);
        $language = (string) (($identity['languages'][0] ?? null) ?: 'en');
        $tone = (string) ($behavior['base_tone'] ?? 'warm, playful');
        $style = 'short casual DM texts (reply_length='.($behavior['reply_length'] ?? 'medium').')';
        $description = trim((string) ($identity['bio'] ?? '').' '.(string) ($identity['backstory'] ?? ''));
        if ($description === '') {
            $description = $settings->displayName.' AI Character';
        }

        $systemRules = array_values(array_filter([
            ...array_map(static fn ($f) => 'Canon: '.$f, (array) ($identity['canon_facts'] ?? [])),
            ...array_map(static fn ($f) => 'Never: '.$f, (array) ($identity['never_says'] ?? [])),
            'Explicit level: '.($identity['explicit_level'] ?? 'suggestive'),
            'Tone: '.$tone,
            'Reply language mode: '.($behavior['reply_language_mode'] ?? 'match_user'),
        ]));

        $persona = new Persona(
            $existing?->id() ?? new PersonaId(bin2hex(random_bytes(16))),
            $tenant,
            $character,
            $name,
            $language,
            $tone,
            $style,
            $description,
            $systemRules !== [] ? $systemRules : ['Stay in character.'],
            [
                'source' => 'character_settings',
                'character_id' => $settings->characterId,
                'character_version' => $settings->version,
                'prompt_preview' => $settings->buildPromptPreview(),
            ],
        );
        $this->personas->save($persona);

        return [
            'character_id' => $settings->characterId,
            'tenant_id' => $settings->tenantId,
            'persona_id' => (string) $persona->id(),
            'display_name' => $settings->displayName,
        ];
    }

    /**
     * @deprecated Use ensurePersonaForCharacter(); kept so old /dev/influencers clients still work.
     *
     * @param  array<string, mixed>  $persona
     * @return array{influencer_id: string, character_id: string, tenant_id: string, persona_id: string}
     */
    public function createInfluencer(string $tenantId, string $name, array $persona = []): array
    {
        $name = trim($name);
        if ($name === '') {
            $name = 'Dev Character';
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
            (string) ($persona['description'] ?? ($persona['persona'] ?? 'An AI Character.')),
            array_values((array) ($persona['system_rules'] ?? ['Be respectful'])),
            [
                'persona' => $persona['persona'] ?? ($persona['description'] ?? null),
                ...$persona,
            ],
        ));

        return [
            'influencer_id' => (string) $influencerId,
            'character_id' => (string) $influencerId,
            'tenant_id' => $tenantId,
            'persona_id' => (string) $personaId,
        ];
    }
}
