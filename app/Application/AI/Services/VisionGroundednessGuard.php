<?php

namespace App\Application\AI\Services;

/**
 * Ensures chat replies for photo turns stay grounded in THIS turn's vision analysis,
 * instead of parroting older photo descriptions from conversation history.
 */
final class VisionGroundednessGuard
{
    /**
     * Phrases that often leak from a prior profile-photo turn.
     *
     * @var list<string>
     */
    private const STALE_PROFILE_MARKERS = [
        'leather jacket',
        'turtleneck',
        'shaved head',
        'midnight café',
        'midnight cafe',
        'smoldering confidence',
        'bar-café',
        'bar-cafe',
    ];

    /**
     * @param  list<array{role?: string, content?: string}>  $recentMessages
     * @return list<array{role: string, content: string}>
     */
    public function messagesForVisionTurn(array $recentMessages, string $userText, string $visionAnalysis): array
    {
        // Nuclear isolation: prior assistant photo descriptions poison weak chat models.
        // Keep only this turn's user ask + the factual vision analysis.
        $userText = trim($userText);
        if ($userText === '' || $userText === '[image]' || $userText === '[media]') {
            $userText = 'describe this photo';
        }

        return [[
            'role' => 'user',
            'content' => implode("\n\n", [
                $userText,
                'PHOTO FACTS (this message only — ignore any older photo talk):',
                trim($visionAnalysis),
                'Reply about THESE facts only. Do not describe a different photo.',
            ]),
        ]];
    }

    public function isGrounded(string $reply, string $visionAnalysis): bool
    {
        $reply = mb_strtolower(trim($reply));
        $vision = mb_strtolower(trim($visionAnalysis));
        if ($reply === '' || $vision === '') {
            return false;
        }

        // Stale profile leak when vision never mentioned those things.
        foreach (self::STALE_PROFILE_MARKERS as $marker) {
            if (str_contains($reply, $marker) && ! str_contains($vision, $marker)) {
                return false;
            }
        }

        $visionTokens = $this->significantTokens($vision);
        if ($visionTokens === []) {
            return true;
        }

        $hits = 0;
        foreach ($visionTokens as $token) {
            if (str_contains($reply, $token)) {
                $hits++;
            }
        }

        // Need at least 2 distinctive overlaps, or 1 if vision is short.
        $need = count($visionTokens) >= 6 ? 2 : 1;

        return $hits >= $need;
    }

    /**
     * Last-resort reply that cannot drift to an old photo.
     */
    public function forcedReplyFromVision(string $visionAnalysis, string $userText = ''): string
    {
        $vision = trim($visionAnalysis);
        $ask = mb_strtolower(trim($userText));
        $wantsDetail = $ask === '' || str_contains($ask, 'detail') || str_contains($ask, 'describe');

        if ($wantsDetail) {
            return $vision.' Want me to send you the soft set? 😉';
        }

        // Keep it short for casual reacts.
        $first = preg_split('/(?<=[.!?])\s+/u', $vision, 2)[0] ?? $vision;

        return trim($first).' 🔥';
    }

    /**
     * @return list<string>
     */
    private function significantTokens(string $text): array
    {
        $stop = [
            'the', 'and', 'with', 'that', 'this', 'from', 'into', 'over', 'under', 'a', 'an', 'of', 'to', 'in', 'on',
            'is', 'are', 'was', 'were', 'be', 'been', 'for', 'as', 'by', 'it', 'its', 'at', 'or', 'image', 'shows',
            'photo', 'picture', 'appears', 'there', 'their', 'has', 'have', 'been', 'various', 'including', 'overall',
        ];
        $parts = preg_split('/[^a-z0-9]+/u', mb_strtolower($text)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            if (strlen($part) < 4) {
                continue;
            }
            if (in_array($part, $stop, true)) {
                continue;
            }
            $out[$part] = true;
        }

        return array_slice(array_keys($out), 0, 24);
    }
}
