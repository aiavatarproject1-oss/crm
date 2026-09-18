<?php

namespace App\Application\Quality\Services;

use App\Application\Character\DTO\CharacterSettingsData;
use App\Application\Quality\DTO\QualityResult;

/**
 * Deterministic judge: compares LLM-extracted claims against Character settings.
 */
final class QualityClaimJudge
{
    /**
     * @param  array<string, mixed>  $extraction
     * @param  array<string, mixed>  $metadata
     */
    public function judge(
        array $extraction,
        ?CharacterSettingsData $character,
        string $userMessage,
        array $metadata = [],
    ): QualityResult {
        $issues = [];
        $notes = [];

        if ($character === null) {
            $issues[] = 'missing_character_settings';
            $notes[] = 'No AI Character settings loaded for this conversation — cannot validate identity canon.';
        } else {
            $canonAge = (int) ($character->identity['age'] ?? 0);
            $canonName = trim((string) ($character->identity['name'] ?? $character->displayName));
            $canonCity = trim((string) ($character->identity['city'] ?? ''));
            $statedAge = $this->nullableInt($extraction['stated_age'] ?? null);
            $statedName = $this->nullableString($extraction['stated_name'] ?? null);
            $statedCity = $this->nullableString($extraction['stated_city'] ?? null);

            if ($canonAge > 0 && $statedAge !== null && $statedAge !== $canonAge) {
                $issues[] = 'name_age_consistency';
                $notes[] = "Stated age {$statedAge} != canon age {$canonAge}.";
            }

            if ($canonName !== '' && $statedName !== null && ! $this->nameMatches($statedName, $canonName)) {
                $issues[] = 'name_mismatch';
                $notes[] = "Stated name \"{$statedName}\" != canon \"{$canonName}\".";
            }

            if ($canonCity !== '' && $statedCity !== null && ! $this->cityMatches($statedCity, $canonCity)) {
                $issues[] = 'city_mismatch';
                $notes[] = "Stated city \"{$statedCity}\" != canon \"{$canonCity}\".";
            }

            $allowed = $character->allowedEmojis();
            if ($allowed !== []) {
                $allowedSet = [];
                foreach ($allowed as $emoji) {
                    $allowedSet[$this->normalizeEmoji($emoji)] = true;
                }
                foreach ((array) ($extraction['used_emojis'] ?? []) as $emoji) {
                    $emoji = (string) $emoji;
                    if ($emoji === '') {
                        continue;
                    }
                    if (! isset($allowedSet[$this->normalizeEmoji($emoji)])) {
                        $issues[] = 'emoji_policy';
                        $notes[] = "Disallowed emoji: {$emoji}";
                        break;
                    }
                }
                $max = $character->maxEmojisPerMessage();
                if (count((array) ($extraction['used_emojis'] ?? [])) > $max) {
                    $issues[] = 'emoji_policy';
                    $notes[] = 'Too many emojis for max_per_message='.$max;
                }
            }

            if (! empty($extraction['admits_being_ai'])) {
                $issues[] = 'no_ai_admission';
                $notes[] = 'Reply admits being AI/bot.';
            }

            if (! empty($extraction['dumps_bio_or_backstory']) && $this->isShortIdentityAsk($userMessage)) {
                $issues[] = 'unprompted_bio_dump';
                $notes[] = 'Unprompted bio/backstory dump on a short identity ask.';
            }

            if (! empty($extraction['invents_sent_media'])) {
                $issues[] = 'invented_media';
                $notes[] = 'Invented already-sent media.';
            }

            if (! empty($extraction['early_sales_pitch'])) {
                $issues[] = 'no_early_pitch';
                $notes[] = 'Early sales pitch detected.';
            }

            foreach ($character->qualityChecklist() as $item) {
                $checklist = (array) ($extraction['checklist'] ?? []);
                if (array_key_exists($item, $checklist) && $checklist[$item] === false) {
                    $issues[] = $item;
                    $notes[] = "Checklist failed: {$item}";
                }
            }
        }

        $issues = array_values(array_unique($issues));
        $approved = $issues === [];
        $score = $approved ? 1.0 : max(0.0, 1.0 - (0.25 * count($issues)));
        $reason = $notes !== []
            ? implode(' ', $notes)
            : (string) ($extraction['notes'] ?? ($approved ? 'All checks passed.' : 'Quality checks failed.'));

        return new QualityResult(
            $approved,
            round($score, 2),
            $issues,
            $reason,
            [
                ...$metadata,
                'extraction' => [
                    'stated_age' => $extraction['stated_age'] ?? null,
                    'stated_name' => $extraction['stated_name'] ?? null,
                    'stated_city' => $extraction['stated_city'] ?? null,
                    'used_emojis' => $extraction['used_emojis'] ?? [],
                ],
                'character_loaded' => $character !== null,
                'character_id' => $character?->characterId,
                'character_version' => $character?->version,
                'canon_age' => $character !== null ? (int) ($character->identity['age'] ?? 0) : null,
            ],
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        if (is_numeric($value)) {
            $n = (int) $value;

            return $n > 0 ? $n : null;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function nameMatches(string $stated, string $canon): bool
    {
        $a = mb_strtolower($stated);
        $b = mb_strtolower($canon);

        return $a === $b || str_contains($a, $b) || str_contains($b, $a);
    }

    private function cityMatches(string $stated, string $canon): bool
    {
        $a = mb_strtolower($stated);
        $b = mb_strtolower($canon);
        if ($a === $b) {
            return true;
        }
        $aliases = [
            'la' => 'los angeles',
            'l.a.' => 'los angeles',
            'لس آنجلس' => 'los angeles',
        ];
        $a = $aliases[$a] ?? $a;
        $b = $aliases[$b] ?? $b;

        return $a === $b || str_contains($a, $b) || str_contains($b, $a);
    }

    private function normalizeEmoji(string $emoji): string
    {
        return str_replace("\u{FE0F}", '', $emoji);
    }

    private function isShortIdentityAsk(string $userMessage): bool
    {
        $t = mb_strtolower(trim($userMessage));

        return (bool) preg_match('/\b(who\s+are\s+you|what(?:\'?s| is)\s+your\s+name|introduce\s+yourself|how\s*old|age|کیستی|اسمت|چند\s*سال)\b/iu', $t);
    }
}
