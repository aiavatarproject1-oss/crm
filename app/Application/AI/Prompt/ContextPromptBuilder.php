<?php

namespace App\Application\AI\Prompt;

use App\Application\AI\Contracts\PromptBuilderInterface;
use App\Application\AI\DTO\PromptPayload;
use App\Application\Context\DTO\ConversationContext;

final readonly class ContextPromptBuilder implements PromptBuilderInterface
{
    public function build(ConversationContext $context, array $metadata = []): PromptPayload
    {
        $persona = $context->influencer_persona->toArray();
        $sections = [
            'Influencer persona: '.json_encode($persona, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'User memory: '.json_encode($context->user_memories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'Relevant knowledge: '.json_encode($context->knowledge_context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        return new PromptPayload(
            implode("\n", $sections),
            $context->recent_messages,
            [
                ...$metadata,
                'user_context' => $context->user_memories,
                'influencer_context' => $persona,
                'knowledge_context' => $context->knowledge_context,
            ],
        );
    }
}
