<?php

namespace App\Application\Context\DTO;

final readonly class ConversationContext
{
    public function __construct(public array $recent_messages, public array $user_memories, public PersonaContext $influencer_persona, public array $knowledge_context = []) {}
}
