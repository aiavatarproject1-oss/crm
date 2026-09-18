<?php

namespace Database\Seeders;

use App\Application\Character\Contracts\CharacterSettingsRepositoryInterface;
use App\Application\Character\DTO\CharacterSettingsData;
use App\Application\Dev\Services\DevSetupService;
use Illuminate\Database\Seeder;

final class CharacterSeeder extends Seeder
{
    public function run(): void
    {
        $repo = app(CharacterSettingsRepositoryInterface::class);
        $tenantId = (string) env('INBOUND_API_KEY_DEMO_TENANT', 'tenant-demo');
        $characterId = (string) env('INBOUND_API_KEY_DEMO_CHARACTERS', env('INBOUND_API_KEY_DEMO_INFLUENCERS', 'character-estelle'));
        $characterId = trim(explode(',', $characterId)[0] ?: 'character-estelle');

        $existing = $repo->findByCharacter($tenantId, $characterId) ?? $repo->findBySlug('estelle');
        if ($existing === null) {
            $defaults = CharacterSettingsData::estelleDefaults($tenantId, $characterId);
            $repo->save($defaults);
            $this->command?->line("  AI Character: {$defaults->displayName} ({$defaults->characterId})");
            $characterId = $defaults->id;
        } else {
            $this->command?->line('  character: '.$existing->slug.' (exists)');
            $characterId = $existing->id;
        }

        $bound = app(DevSetupService::class)->ensurePersonaForCharacter($characterId);
        $this->command?->line('  runtime persona bound: '.$bound['persona_id']);
    }
}
