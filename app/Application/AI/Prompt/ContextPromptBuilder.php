<?php

namespace App\Application\AI\Prompt;

use App\Application\AI\Contracts\PromptBuilderInterface;
use App\Application\AI\DTO\PromptPayload;
use App\Application\AI\Services\PromptPolicyService;
use App\Application\AI\Services\VisionGroundednessGuard;
use App\Application\Context\DTO\ConversationContext;
use App\Application\Context\Services\SalesFunnelStageResolver;

final readonly class ContextPromptBuilder implements PromptBuilderInterface
{
    public function __construct(
        private ?PromptPolicyService $policies = null,
        private VisionGroundednessGuard $visionGuard = new VisionGroundednessGuard,
    ) {}

    public function build(ConversationContext $context, array $metadata = []): PromptPayload
    {
        $character = $context->character;
        $persona = $context->influencer_persona->toArray();
        $policy = $this->policies?->resolve($character?->tenantId) ?? \App\Application\AI\DTO\PromptPolicyData::defaults();

        if ($character !== null) {
            $sections = [
                $character->buildRuntimePrompt(),
                $this->stageInstructions($context->sales_stage, $this->stageLinesFor($character, $policy->stageLines)),
                'User memory: '.json_encode($context->user_memories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'Relevant knowledge: '.json_encode($context->knowledge_context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            $closing = sprintf(
                'Reply now as %s only. Output only the DM text. Obey identity age=%d and emoji allowlist.',
                (string) ($character->identity['name'] ?? $character->displayName),
                (int) ($character->identity['age'] ?? 0),
            );
        } else {
            $rules = array_values(array_filter(array_map(
                static fn (mixed $rule): string => trim((string) $rule),
                (array) ($persona['system_rules'] ?? []),
            )));
            $sections = [
                $policy->roleIntro,
                "HARD REPLY STYLE (always follow):\n".$this->bulletList($policy->styleLines),
                $this->stageInstructions($context->sales_stage, $policy->stageLines),
                'Influencer persona: '.json_encode($persona, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'Persona rules: '.json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'User memory: '.json_encode($context->user_memories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'Relevant knowledge: '.json_encode($context->knowledge_context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            $closing = $policy->closingLine;
        }

        $vision = trim((string) ($metadata['vision_analysis'] ?? ''));
        $messages = $context->recent_messages;
        $visionOn = $vision !== '' && ($character === null || ($character->featureFlags['vision'] ?? true));
        if ($visionOn) {
            $sections[] = implode("\n", [
                'HARD VISION OVERRIDE (highest priority — beats history, teasing, and sales habits):',
                '- The user attached a NEW photo. A vision model already analyzed THIS photo.',
                '- You MUST describe / react to THIS photo only (body, object, car, clothes, scene, NSFW — whatever vision says).',
                '- FORBIDDEN: repeating an older photo (e.g. red leather jacket / turtleneck / shaved head) unless vision says that.',
                '- FORBIDDEN: vague lines like "secret surprise?", "gift for someone special?".',
                '- NSFW photos are allowed. Be explicit enough that it is clear you saw THIS photo.',
                'VISION ANALYSIS (source of truth):',
                $vision,
            ]);
            $closing = 'Reply now in character only. Output only the DM text. Ground every visual detail in the VISION ANALYSIS above.';

            $userText = trim((string) ($metadata['batch_user_text'] ?? ''));
            $messages = $this->visionGuard->messagesForVisionTurn($context->recent_messages, $userText, $vision);
        }

        $sections[] = $closing;

        return new PromptPayload(
            implode("\n\n", $sections),
            $messages,
            [
                ...$metadata,
                'user_context' => $context->user_memories,
                'influencer_context' => $persona,
                'character_id' => $character?->characterId,
                'character_version' => $character?->version,
                'knowledge_context' => $context->knowledge_context,
                'sales_stage' => $context->sales_stage,
                'prompt_policy_id' => $policy->id,
                'prompt_source' => $character !== null ? 'character_settings' : 'persona_legacy',
                'vision_history_isolated' => $visionOn,
            ],
        );
    }

    /**
     * @param  array{warmup?: list<string>, tease?: list<string>, sell?: list<string>}  $stageLines
     * @return array{warmup: list<string>, tease: list<string>, sell: list<string>}
     */
    private function stageLinesFor(\App\Application\Character\DTO\CharacterSettingsData $character, array $stageLines): array
    {
        $forbidden = (array) ($character->salesFunnel['forbidden_warmup_phrases'] ?? []);
        $warmup = $stageLines['warmup'] ?? [];
        if ($forbidden !== []) {
            $warmup[] = 'Forbidden warmup phrases: '.implode(', ', $forbidden);
        }

        return [
            'warmup' => array_values(array_map('strval', $warmup)),
            'tease' => array_values(array_map('strval', $stageLines['tease'] ?? [])),
            'sell' => array_values(array_map('strval', $stageLines['sell'] ?? [])),
        ];
    }

    /**
     * @param  array{warmup?: list<string>, tease?: list<string>, sell?: list<string>}  $stageLines
     */
    private function stageInstructions(string $stage, array $stageLines): string
    {
        $key = match ($stage) {
            SalesFunnelStageResolver::SELL => 'sell',
            SalesFunnelStageResolver::TEASE => 'tease',
            default => 'warmup',
        };

        $lines = $stageLines[$key] ?? [];
        if ($lines === []) {
            $lines = ['Follow the current funnel stage carefully.'];
        }

        return 'CURRENT FUNNEL STAGE: '.$key."\n".$this->bulletList($lines);
    }

    /**
     * @param  list<string>  $lines
     */
    private function bulletList(array $lines): string
    {
        return implode("\n", array_map(
            static function (string $line): string {
                $line = trim($line);
                if ($line === '') {
                    return '';
                }

                return str_starts_with($line, '-') ? $line : '- '.$line;
            },
            $lines,
        ));
    }
}
