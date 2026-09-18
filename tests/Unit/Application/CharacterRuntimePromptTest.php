<?php

namespace Tests\Unit\Application;

use App\Application\AI\Services\EmojiPolicyEnforcer;
use App\Application\Character\DTO\CharacterSettingsData;
use PHPUnit\Framework\TestCase;

final class CharacterRuntimePromptTest extends TestCase
{
    public function test_runtime_prompt_uses_identity_age_not_stale_canon(): void
    {
        $character = CharacterSettingsData::fromDocument([
            ...CharacterSettingsData::estelleDefaults()->toArray(),
            '_id' => 'character-estelle',
            'identity' => [
                'age' => 31,
                'canon_facts' => ['Age is 27', 'Name is Estelle', 'Loves cats'],
            ],
        ]);

        $prompt = $character->buildRuntimePrompt();

        self::assertStringContainsString('Age is exactly 31', $prompt);
        self::assertStringContainsString('- Age is 31', $prompt);
        self::assertStringNotContainsString('Age is 27', $prompt);
        self::assertStringContainsString('who are you', $prompt);
    }

    public function test_emoji_policy_strips_disallowed_emoji(): void
    {
        $character = CharacterSettingsData::estelleDefaults();
        $enforcer = new EmojiPolicyEnforcer;
        $out = $enforcer->apply('Thanks, you\'re sweet 😏😊', $character);

        self::assertStringNotContainsString('😏', $out);
        self::assertStringContainsString('😊', $out);
    }
}
