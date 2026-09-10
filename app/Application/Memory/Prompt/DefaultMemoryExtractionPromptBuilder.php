<?php

namespace App\Application\Memory\Prompt;

use App\Application\Memory\Contracts\MemoryExtractionPromptBuilderInterface;
use App\Application\Memory\DTO\MemoryExtractionContext;
use App\Application\Memory\DTO\MemoryExtractionPrompt;
use App\Domain\Memory\ValueObjects\MemoryType;

final readonly class DefaultMemoryExtractionPromptBuilder implements MemoryExtractionPromptBuilderInterface
{
    public function build(MemoryExtractionContext $context): MemoryExtractionPrompt
    {
        $types = implode(', ', [
            MemoryType::PROFILE,
            MemoryType::PREFERENCE,
            MemoryType::FACT,
            MemoryType::RELATIONSHIP,
            MemoryType::EVENT,
            MemoryType::GOAL,
        ]);

        $system = <<<PROMPT
You extract durable user memories from one conversation turn.
Return ONLY valid JSON with this shape:
{"candidates":[{"type":"FACT","content":"...","confidence_score":0.0,"importance_score":0.0,"metadata":{}}]}
Allowed types: {$types}.
Scores must be numbers between 0 and 1.
If nothing durable is found, return {"candidates":[]}.
Do not invent facts that are not supported by the messages.
PROMPT;

        $lines = [];
        foreach ($context->messages as $index => $message) {
            $n = $index + 1;
            $lines[] = "{$n}. [{$message['sender']}] {$message['content']}";
        }
        $messageBlock = $lines === [] ? '(no messages)' : implode("\n", $lines);

        $user = <<<PROMPT
Tenant: {$context->tenantId}
Influencer: {$context->influencerId}
User: {$context->userId}
Batch: {$context->batchId}
Platform: {$context->platform}

Messages:
{$messageBlock}
PROMPT;

        return new MemoryExtractionPrompt($system, $user, [
            'purpose' => 'memory_extraction',
            'batch_id' => (string) $context->batchId,
            'message_count' => count($context->messages),
        ]);
    }
}
