<?php

namespace App\Application\AI\Contracts;

use App\Application\AI\DTO\PromptPayload;
use App\Application\Context\DTO\ConversationContext;

interface PromptBuilderInterface
{
    public function build(ConversationContext $context, array $metadata = []): PromptPayload;
}
