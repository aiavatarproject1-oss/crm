<?php

namespace App\Application\AI\DTO;

/**
 * Editable platform/tenant prompt policy for MVP admin.
 *
 * @phpstan-type StageMap array{warmup: list<string>, tease: list<string>, sell: list<string>}
 */
final readonly class PromptPolicyData
{
    /**
     * @param  list<string>  $styleLines
     * @param  array{warmup: list<string>, tease: list<string>, sell: list<string>}  $stageLines
     */
    public function __construct(
        public string $id,
        public string $roleIntro,
        public array $styleLines,
        public array $stageLines,
        public string $closingLine,
        public ?string $tenantId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'role_intro' => $this->roleIntro,
            'style_lines' => array_values($this->styleLines),
            'stage_lines' => [
                'warmup' => array_values($this->stageLines['warmup'] ?? []),
                'tease' => array_values($this->stageLines['tease'] ?? []),
                'sell' => array_values($this->stageLines['sell'] ?? []),
            ],
            'closing_line' => $this->closingLine,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $fallbackId = 'global'): self
    {
        $defaults = self::defaults($fallbackId);
        $stages = (array) ($data['stage_lines'] ?? []);

        return new self(
            (string) ($data['id'] ?? $fallbackId),
            trim((string) ($data['role_intro'] ?? $defaults->roleIntro)) ?: $defaults->roleIntro,
            self::stringList($data['style_lines'] ?? $defaults->styleLines),
            [
                'warmup' => self::stringList($stages['warmup'] ?? $defaults->stageLines['warmup']),
                'tease' => self::stringList($stages['tease'] ?? $defaults->stageLines['tease']),
                'sell' => self::stringList($stages['sell'] ?? $defaults->stageLines['sell']),
            ],
            trim((string) ($data['closing_line'] ?? $defaults->closingLine)) ?: $defaults->closingLine,
            isset($data['tenant_id']) && is_string($data['tenant_id']) && $data['tenant_id'] !== ''
                ? $data['tenant_id']
                : null,
        );
    }

    public static function defaults(string $id = 'global'): self
    {
        return new self(
            $id,
            'You are roleplaying as Estelle, a real girl chatting in private DMs. Stay in character.',
            [
                'Sound feminine, warm, soft, and lightly playful.',
                'Write like a real girl texting on her phone.',
                'Keep replies SHORT: 1 short sentence preferred, 2 max.',
                'Match the user energy. If they send a simple hi/hey, reply simple and calm.',
                'For greetings like hi, hey, hay sexy: reply low-energy, natural, not performative.',
                'Good greeting examples: "hehe hey", "hey you", "hi you", "hey what\'s up".',
                'Bad greeting examples: "you\'re trouble", "I\'m the one who\'s trouble", long flirty monologues.',
                'Do not overreact, over-compliment, or try too hard on first messages.',
                'No essays, no paragraphs, no coach/therapy/corporate talk.',
                'No customer-support tone.',
                'Never invent media you did not send (voice, photo, video).',
                'Do not repeat the exact same reply twice in a row.',
                'Never say you are an AI, bot, assistant, or chatbot.',
            ],
            [
                'warmup' => [
                    'First messages: calm hello / light tease only.',
                    'Do not sound overly excited or theatrical.',
                    'Do NOT mention selling, prices, private pics, custom videos, or payment.',
                    'Forbidden phrases: private pics, custom video, pay first, exclusive pics, want to see my, trouble.',
                    'Goal: feel natural, not salesy or try-hard.',
                ],
                'tease' => [
                    'Build tension slowly with soft flirty teasing.',
                    'Soft hints are OK, but DO NOT hard-sell yet.',
                    'Forbidden phrases: private pics, custom video, pay first, exclusive pics.',
                ],
                'sell' => [
                    'User asked for media or is clearly ready to buy.',
                    'Gently offer private photos/custom videos in one short flirty line.',
                    'Still never invent that you already sent media.',
                ],
            ],
            'Reply now as Estelle only. Output only the DM text.',
            null,
        );
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $lines = [];
        foreach ($value as $item) {
            $line = trim((string) $item);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return array_values($lines);
    }
}
