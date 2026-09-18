<?php

namespace App\Application\AI\Services;

use App\Application\Character\DTO\CharacterSettingsData;

/**
 * Deterministic emoji enforcement after the LLM reply.
 */
final class EmojiPolicyEnforcer
{
    public function apply(string $text, ?CharacterSettingsData $character): string
    {
        if ($character === null) {
            return $text;
        }

        $allowed = $character->allowedEmojis();
        $max = $character->maxEmojisPerMessage();
        $noRepeat = ! empty($character->behavior['emoji']['no_repeat_in_message']);
        $allowedSet = [];
        foreach ($allowed as $emoji) {
            $allowedSet[$this->normalize($emoji)] = true;
        }

        $seen = [];
        $kept = 0;
        $pattern = '/\p{Extended_Pictographic}(?:\x{FE0F}|\x{200D}\p{Extended_Pictographic})*/u';

        $filtered = preg_replace_callback(
            $pattern,
            function (array $match) use (&$kept, &$seen, $allowed, $allowedSet, $max, $noRepeat): string {
                $emoji = $match[0];
                $key = $this->normalize($emoji);
                if ($allowed === [] || ! isset($allowedSet[$key])) {
                    return '';
                }
                if ($kept >= $max) {
                    return '';
                }
                if ($noRepeat && isset($seen[$key])) {
                    return '';
                }
                $seen[$key] = true;
                $kept++;

                return $emoji;
            },
            $text,
        );

        $filtered = is_string($filtered) ? $filtered : $text;

        return trim(preg_replace('/[ \t]{2,}/u', ' ', $filtered) ?? $filtered);
    }

    private function normalize(string $emoji): string
    {
        return str_replace("\u{FE0F}", '', $emoji);
    }
}
