<?php

namespace Tests\Unit\Application;

use App\Application\Character\DTO\CharacterSettingsData;
use App\Application\Quality\Services\QualityClaimJudge;
use PHPUnit\Framework\TestCase;

final class QualityClaimJudgeTest extends TestCase
{
    public function test_rejects_wrong_stated_age_against_character(): void
    {
        $character = CharacterSettingsData::fromDocument([
            ...CharacterSettingsData::estelleDefaults()->toArray(),
            'identity' => ['age' => 31, 'name' => 'Estelle', 'city' => 'Los Angeles'],
        ]);

        $result = (new QualityClaimJudge)->judge(
            ['stated_age' => 17, 'stated_name' => 'Estelle', 'used_emojis' => ['😊']],
            $character,
            'how old are U?',
        );

        self::assertFalse($result->approved);
        self::assertContains('name_age_consistency', $result->issues);
    }

    public function test_accepts_matching_age(): void
    {
        $character = CharacterSettingsData::fromDocument([
            ...CharacterSettingsData::estelleDefaults()->toArray(),
            'identity' => ['age' => 31, 'name' => 'Estelle', 'city' => 'Los Angeles'],
        ]);

        $result = (new QualityClaimJudge)->judge(
            ['stated_age' => 31, 'stated_name' => 'Estelle', 'used_emojis' => ['😊']],
            $character,
            'how old are you?',
        );

        self::assertTrue($result->approved);
    }

    public function test_fails_closed_without_character_settings(): void
    {
        $result = (new QualityClaimJudge)->judge(
            ['stated_age' => 17],
            null,
            'how old are you?',
        );

        self::assertFalse($result->approved);
        self::assertContains('missing_character_settings', $result->issues);
    }
}
