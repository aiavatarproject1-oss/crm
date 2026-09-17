<?php

namespace App\Application\Context\DTO;

use App\Application\Context\Services\SalesFunnelStageResolver;

final readonly class ConversationContext
{
    /**
     * @param  list<array<string, mixed>>  $recent_messages
     * @param  list<array<string, mixed>>  $user_memories
     * @param  list<array<string, mixed>>  $knowledge_context
     */
    public function __construct(
        public array $recent_messages,
        public array $user_memories,
        public PersonaContext $influencer_persona,
        public array $knowledge_context = [],
        public string $sales_stage = SalesFunnelStageResolver::WARMUP,
    ) {}
}
