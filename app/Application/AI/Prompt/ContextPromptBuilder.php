<?php

namespace App\Application\AI\Prompt;

use App\Application\AI\Contracts\PromptBuilderInterface;
use App\Application\AI\DTO\PromptPayload;
use App\Application\AI\Services\PromptPolicyService;
use App\Application\Context\DTO\ConversationContext;
use App\Application\Context\Services\SalesFunnelStageResolver;

final readonly class ContextPromptBuilder implements PromptBuilderInterface
{
    public function __construct(private ?PromptPolicyService $policies = null) {}

    public function build(ConversationContext $context, array $metadata = []): PromptPayload
    {
        $persona = $context->influencer_persona->toArray();
        $rules = array_values(array_filter(array_map(
            static fn (mixed $rule): string => trim((string) $rule),
            (array) ($persona['system_rules'] ?? []),
        )));

        $policy = $this->policies?->resolve(null) ?? \App\Application\AI\DTO\PromptPolicyData::defaults();

        $styleBlock = "HARD REPLY STYLE (always follow):\n".$this->bulletList($policy->styleLines);
        $sections = [
            $policy->roleIntro,
            $styleBlock,
            $this->stageInstructions($context->sales_stage, $policy->stageLines),
            'Influencer persona: '.json_encode($persona, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Persona rules: '.json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'User memory: '.json_encode($context->user_memories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Relevant knowledge: '.json_encode($context->knowledge_context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $policy->closingLine,
        ];

        return new PromptPayload(
            implode("\n", $sections),
            $context->recent_messages,
            [
                ...$metadata,
                'user_context' => $context->user_memories,
                'influencer_context' => $persona,
                'knowledge_context' => $context->knowledge_context,
                'sales_stage' => $context->sales_stage,
                'prompt_policy_id' => $policy->id,
            ],
        );
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
