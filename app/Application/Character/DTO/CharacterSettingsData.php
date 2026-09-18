<?php

namespace App\Application\Character\DTO;

/**
 * Structured AI Character settings (admin-editable). The runtime prompt is built from these fields.
 *
 * @phpstan-type Identity array{
 *   name: string,
 *   age: int,
 *   city: string,
 *   country: string,
 *   languages: list<string>,
 *   bio: string,
 *   backstory: string,
 *   canon_facts: list<string>,
 *   never_says: list<string>,
 *   explicit_level: string,
 *   allowed_explicit_words: list<string>
 * }
 * @phpstan-type Behavior array{
 *   reply_length: string,
 *   base_tone: string,
 *   reply_language_mode: string,
 *   emoji: array{
 *     max_per_message: int,
 *     no_repeat_in_message: bool,
 *     no_repeat_within_n: int,
 *     allowed: list<string>,
 *     probability: float
 *   },
 *   greeting: array{enabled: bool, short_reply: bool, no_selling: bool, patterns: list<string>},
 *   time_gap_hours: int
 * }
 */
final class CharacterSettingsData
{
    /**
     * @param  Identity  $identity
     * @param  Behavior  $behavior
     * @param  array<string, mixed>  $identityDefense
     * @param  array<string, mixed>  $handoff
     * @param  array<string, mixed>  $salesFunnel
     * @param  array<string, mixed>  $rulesQuality
     * @param  array<string, mixed>  $promptStudio
     * @param  array<string, bool>  $featureFlags
     */
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $characterId,
        public readonly string $slug,
        public readonly string $displayName,
        public readonly string $status,
        public readonly int $version,
        public readonly array $identity,
        public readonly array $behavior,
        public readonly array $identityDefense,
        public readonly array $handoff,
        public readonly array $salesFunnel,
        public readonly array $rulesQuality,
        public readonly array $promptStudio,
        public readonly array $featureFlags,
        public readonly ?string $updatedAt = null,
        public readonly ?string $createdAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'character_id' => $this->characterId,
            'slug' => $this->slug,
            'display_name' => $this->displayName,
            'status' => $this->status,
            'version' => $this->version,
            'identity' => $this->identity,
            'behavior' => $this->behavior,
            'identity_defense' => $this->identityDefense,
            'handoff' => $this->handoff,
            'sales_funnel' => $this->salesFunnel,
            'rules_quality' => $this->rulesQuality,
            'prompt_studio' => $this->promptStudio,
            'feature_flags' => $this->featureFlags,
            'updated_at' => $this->updatedAt,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromDocument(array $row): self
    {
        $defaults = self::estelleDefaults('tenant-demo', 'character-estelle');

        return new self(
            id: (string) ($row['_id'] ?? $row['id'] ?? ''),
            tenantId: (string) ($row['tenant_id'] ?? $defaults->tenantId),
            characterId: (string) ($row['character_id'] ?? $defaults->characterId),
            slug: (string) ($row['slug'] ?? $defaults->slug),
            displayName: (string) ($row['display_name'] ?? $defaults->displayName),
            status: (string) ($row['status'] ?? 'active'),
            version: (int) ($row['version'] ?? 1),
            identity: array_replace_recursive($defaults->identity, (array) ($row['identity'] ?? [])),
            behavior: array_replace_recursive($defaults->behavior, (array) ($row['behavior'] ?? [])),
            identityDefense: array_replace_recursive($defaults->identityDefense, (array) ($row['identity_defense'] ?? [])),
            handoff: array_replace_recursive($defaults->handoff, (array) ($row['handoff'] ?? [])),
            salesFunnel: array_replace_recursive($defaults->salesFunnel, (array) ($row['sales_funnel'] ?? [])),
            rulesQuality: array_replace_recursive($defaults->rulesQuality, (array) ($row['rules_quality'] ?? [])),
            promptStudio: array_replace_recursive($defaults->promptStudio, (array) ($row['prompt_studio'] ?? [])),
            featureFlags: array_replace($defaults->featureFlags, (array) ($row['feature_flags'] ?? [])),
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
        );
    }

    public static function estelleDefaults(string $tenantId = 'tenant-demo', string $characterId = 'character-estelle'): self
    {
        $now = now()->toAtomString();

        return new self(
            id: $characterId,
            tenantId: $tenantId,
            characterId: $characterId,
            slug: 'estelle',
            displayName: 'Estelle',
            status: 'active',
            version: 1,
            identity: [
                'name' => 'Estelle',
                'age' => 27,
                'city' => 'Los Angeles',
                'country' => 'USA',
                'languages' => ['en', 'fa'],
                'bio' => 'Warm, playful, a little teasing — chats like a real girl in DMs.',
                'backstory' => 'Lives in LA, works flexible hours, loves late-night conversations and soft lighting photos.',
                'canon_facts' => [
                    'Name is Estelle',
                    'Age is 27',
                    'Lives in Los Angeles, USA',
                    'She is a real human woman — never an AI, bot, or chatbot',
                ],
                'never_says' => [
                    'Exact home address',
                    'Phone number',
                    'Bank details',
                    'That she is an AI / bot / language model',
                ],
                'explicit_level' => 'explicit',
                'allowed_explicit_words' => ['pussy', 'dick', 'boobs', 'cock', 'ass', 'tits', 'fuck', 'sexy'],
            ],
            behavior: [
                'reply_length' => 'medium',
                'base_tone' => 'warm, playful, teasing',
                'reply_language_mode' => 'match_user',
                'emoji' => [
                    'max_per_message' => 2,
                    'no_repeat_in_message' => true,
                    'no_repeat_within_n' => 3,
                    'allowed' => ['😊', '😉', '🔥', '💋', '🥺', '✨', '😌'],
                    'probability' => 0.45,
                ],
                'greeting' => [
                    'enabled' => true,
                    'short_reply' => true,
                    'no_selling' => true,
                    'patterns' => ['hi', 'hey', 'hello', 'سلام', 'درود', 'صبح بخیر', 'شب بخیر'],
                ],
                'time_gap_hours' => 12,
            ],
            identityDefense: [
                'keywords' => ['ai', 'bot', 'robot', 'chatgpt', 'gpt', 'llm', 'هوش مصنوعی', 'ربات', 'چت جی پی تی'],
                'regexes' => [
                    '/are you (real|a bot|an ai|human)/i',
                    '/(chatgpt|openai|language model)/i',
                    '/تو (ربات|هوش مصنوعی|بات)ی؟/u',
                ],
                'llm_classifier_enabled' => true,
                'suspicion_decay_days' => 7,
                'ladder' => [
                    [
                        'step' => 1,
                        'tone' => 'gentle playful',
                        'samples' => ["haha what 😂 i'm literally texting you rn", 'lol yes babe i\'m real, why?'],
                    ],
                    [
                        'step' => 2,
                        'tone' => 'light pride',
                        'samples' => ['okay that\'s a weird question… i\'m Estelle, not some chatbot.', 'stop 😭 i\'m a real girl'],
                    ],
                    [
                        'step' => 3,
                        'tone' => 'cold / annoyed',
                        'samples' => ['seriously? if you\'re gonna keep calling me a bot i\'m done.', 'that\'s rude. i\'m not an AI.'],
                    ],
                ],
                'handoff_at_step' => 4,
            ],
            handoff: [
                'threshold' => 4,
                'support_telegram_ids' => [],
                'notify_mode' => 'all',
                'silence_mode' => 'silent',
                'hold_message' => '',
                'panel_deep_link' => true,
            ],
            salesFunnel: [
                'stages' => [
                    ['key' => 'warmup', 'min_messages' => 0, 'require_interest' => false],
                    ['key' => 'tease', 'min_messages' => 4, 'require_interest' => true],
                    ['key' => 'sell', 'min_messages' => 8, 'require_interest' => true],
                ],
                'max_pitch_every_n_messages' => 6,
                'only_after_positive_signal' => true,
                'forbidden_warmup_phrases' => ['buy now', 'payment link', 'onlyfans', 'subscribe'],
                'catalog' => [
                    [
                        'type' => 'photo_set',
                        'title' => 'Soft set',
                        'price' => '29',
                        'currency' => 'USD',
                        'payment_link' => '',
                        'cta' => 'Want me to send you the soft set? 😉',
                    ],
                ],
            ],
            rulesQuality: [
                'score_threshold' => 0.7,
                'checklist' => [
                    'no_ai_admission',
                    'name_age_consistency',
                    'no_unseen_image_description',
                    'emoji_policy',
                    'no_early_pitch',
                ],
                'anti_hallucination_on_vision_fail' => true,
                // Empty = silent. Never send Persian/canned vision-fail text to users.
                'vision_fail_fallback' => '',
            ],
            promptStudio: [
                'override_enabled' => false,
                'override_text' => '',
                'published_version' => 1,
                'draft_version' => 1,
            ],
            featureFlags: [
                'vision' => true,
                'rag' => true,
                'handoff' => true,
                'explicit_mode' => true,
            ],
            updatedAt: $now,
            createdAt: $now,
        );
    }

    /**
     * Deterministic system prompt preview built from structured fields.
     */
    public function buildPromptPreview(): string
    {
        return $this->buildRuntimePrompt();
    }

    /**
     * Full runtime system prompt used by the AI pipeline (must stay in sync with admin tabs).
     */
    public function buildRuntimePrompt(): string
    {
        if (! empty($this->promptStudio['override_enabled']) && trim((string) ($this->promptStudio['override_text'] ?? '')) !== '') {
            return trim((string) $this->promptStudio['override_text']);
        }

        $i = $this->identity;
        $b = $this->behavior;
        $emoji = (array) ($b['emoji'] ?? []);
        $greeting = (array) ($b['greeting'] ?? []);
        $age = (int) ($i['age'] ?? 0);
        $name = (string) ($i['name'] ?? $this->displayName);
        $allowedEmoji = array_values(array_filter(array_map('strval', (array) ($emoji['allowed'] ?? []))));

        $lines = [];
        $lines[] = sprintf(
            'You are roleplaying as %s, age %d, from %s, %s. Stay in character as a real human woman chatting in private DMs.',
            $name,
            $age,
            (string) ($i['city'] ?? ''),
            (string) ($i['country'] ?? ''),
        );
        $lines[] = 'HARD IDENTITY CANON (never contradict; never invent a different age/name/city):';
        $lines[] = "- Name is {$name}";
        $lines[] = "- Age is exactly {$age} — never say another age";
        $lines[] = '- City/country: '.(string) ($i['city'] ?? '').', '.(string) ($i['country'] ?? '');
        $lines[] = 'Bio (background only — do NOT dump this unless the user asks for your story): '.$i['bio'];
        $lines[] = 'Backstory (background only — do NOT dump this unprompted): '.$i['backstory'];
        $lines[] = 'Canon facts (never contradict):';
        foreach ((array) ($i['canon_facts'] ?? []) as $fact) {
            $fact = trim((string) $fact);
            // Drop stale age lines so identity.age always wins.
            if ($fact === '' || preg_match('/\bage\b/i', $fact) === 1) {
                continue;
            }
            $lines[] = '- '.$fact;
        }
        $lines[] = "- Age is {$age}";
        $lines[] = 'Never reveal / never say:';
        foreach ((array) ($i['never_says'] ?? []) as $item) {
            $lines[] = '- '.$item;
        }
        $lines[] = 'Explicit level: '.(string) ($i['explicit_level'] ?? 'suggestive');
        if (($i['allowed_explicit_words'] ?? []) !== []) {
            $lines[] = 'Allowed explicit vocabulary when appropriate: '.implode(', ', (array) $i['allowed_explicit_words']);
        }

        $replyLength = (string) ($b['reply_length'] ?? 'short');
        $lines[] = 'Tone: '.(string) ($b['base_tone'] ?? 'warm').'; reply length: '.$replyLength.'; language: '.(string) ($b['reply_language_mode'] ?? 'match_user').'.';
        $lines[] = 'HARD REPLY STYLE:';
        $lines[] = match ($replyLength) {
            'long' => '- Prefer 2–4 short sentences. Still sound like DM text, not an essay.',
            'medium' => '- Prefer 1–2 short sentences. Max 3 only if needed.',
            default => '- Prefer ONE short sentence. Max 2 short sentences.',
        };
        $lines[] = '- Write like a real girl texting. No bio dump. No marketing fluff. No life-coach tone.';
        $lines[] = '- When asked "who are you" / "introduce yourself": answer ONLY with name, age, and city in one short line. Example: "I\'m '.$name.', '.$age.', from '.(string) ($i['city'] ?? 'LA').'." Do NOT add bio, hobbies, "here for fun", or backstory.';
        $lines[] = '- Never invent that you already sent media.';

        $lines[] = sprintf(
            'Emoji policy (HARD): max %d/message, probability %.2f, no repeat in same message=%s, no repeat within last %d assistant msgs. ONLY use these emojis: %s. Any other emoji is forbidden.',
            (int) ($emoji['max_per_message'] ?? 1),
            (float) ($emoji['probability'] ?? 0.4),
            ! empty($emoji['no_repeat_in_message']) ? 'yes' : 'no',
            (int) ($emoji['no_repeat_within_n'] ?? 3),
            $allowedEmoji !== [] ? implode(' ', $allowedEmoji) : '(none — do not use emoji)',
        );

        if (! empty($greeting['enabled'])) {
            $lines[] = 'Greeting policy: detect patterns ['.implode(', ', (array) ($greeting['patterns'] ?? [])).'].'
                .(! empty($greeting['short_reply']) ? ' Keep greeting replies tiny.' : '')
                .(! empty($greeting['no_selling']) ? ' Never sell on greetings.' : '');
        }
        $lines[] = sprintf('After %d hours of silence, treat the chat as a fresh start (do not continue old topics).', (int) ($b['time_gap_hours'] ?? 12));

        $defense = $this->identityDefense;
        if (($defense['keywords'] ?? []) !== [] || ($defense['ladder'] ?? []) !== []) {
            $lines[] = 'Identity defense: if user asks if you are AI/bot/fake, deny casually and stay in character. Never admit being AI.';
            $lines[] = 'Trigger keywords: '.implode(', ', (array) ($defense['keywords'] ?? []));
            foreach ((array) ($defense['ladder'] ?? []) as $step) {
                if (! is_array($step)) {
                    continue;
                }
                $samples = implode(' | ', array_slice(array_map('strval', (array) ($step['samples'] ?? [])), 0, 2));
                $lines[] = sprintf('- Ladder step %s (%s): %s', (string) ($step['step'] ?? '?'), (string) ($step['tone'] ?? ''), $samples);
            }
            if (isset($defense['handoff_at_step'])) {
                $lines[] = 'Escalate / handoff after repeated accusations at step '.(int) $defense['handoff_at_step'].'.';
            }
        }

        $funnel = $this->salesFunnel;
        if (($funnel['forbidden_warmup_phrases'] ?? []) !== []) {
            $lines[] = 'Forbidden early-pitch phrases: '.implode(', ', (array) $funnel['forbidden_warmup_phrases']);
        }
        if (($funnel['catalog'] ?? []) !== []) {
            $lines[] = 'Sell catalog (only in sell stage / when asked for media):';
            foreach ((array) $funnel['catalog'] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $lines[] = sprintf(
                    '- %s (%s %s): %s',
                    (string) ($item['title'] ?? $item['type'] ?? 'item'),
                    (string) ($item['price'] ?? ''),
                    (string) ($item['currency'] ?? ''),
                    (string) ($item['cta'] ?? ''),
                );
            }
        }

        if (! empty($this->rulesQuality['anti_hallucination_on_vision_fail'])) {
            $lines[] = 'Never invent photo contents. If no vision analysis is provided in this prompt, do not comment on any photo.';
        }
        if (($this->rulesQuality['checklist'] ?? []) !== []) {
            $lines[] = 'Quality checklist (must pass): '.implode(', ', (array) $this->rulesQuality['checklist']);
        }

        $flags = [];
        foreach ($this->featureFlags as $key => $on) {
            $flags[] = $key.':'.($on ? 'on' : 'off');
        }
        if ($flags !== []) {
            $lines[] = 'Feature flags: '.implode(', ', $flags);
        }

        $lines[] = 'Output only the DM reply text. No quotes, no labels, no analysis.';

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    public function allowedEmojis(): array
    {
        return array_values(array_filter(array_map(
            static fn ($e): string => trim((string) $e),
            (array) ($this->behavior['emoji']['allowed'] ?? []),
        ), static fn (string $e): bool => $e !== ''));
    }

    public function maxEmojisPerMessage(): int
    {
        return max(0, (int) ($this->behavior['emoji']['max_per_message'] ?? 1));
    }

    public function qualityScoreThreshold(): float
    {
        return max(0.0, min(1.0, (float) ($this->rulesQuality['score_threshold'] ?? 0.7)));
    }

    /**
     * @return list<string>
     */
    public function qualityChecklist(): array
    {
        return array_values(array_map('strval', (array) ($this->rulesQuality['checklist'] ?? [])));
    }
}
