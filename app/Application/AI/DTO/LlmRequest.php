<?php

namespace App\Application\AI\DTO;

final readonly class LlmRequest
{
    public function __construct(public string $conversation_id, public array $messages, public string $system_prompt, public array $metadata = [], public array $user_context = [], public array $influencer_context = [], public array $knowledge_context = []) {}

    public static function fromPromptPayload(string $conversationId, PromptPayload $payload): self
    {
        return new self(
            $conversationId,
            $payload->messages,
            $payload->system_prompt,
            $payload->metadata,
            (array) ($payload->metadata['user_context'] ?? []),
            (array) ($payload->metadata['influencer_context'] ?? []),
            (array) ($payload->metadata['knowledge_context'] ?? []),
        );
    }
}
