<?php

namespace App\Application\Context;

use App\Application\Context\DTO\ConversationContext;
use App\Application\Contracts\MemoryRepositoryInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\RAG\Contracts\KnowledgeRetrieverInterface;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Message\Entities\Message;

final readonly class BuildConversationContextHandler
{
    public function __construct(private MessageRepositoryInterface $messages, private MemoryRepositoryInterface $memories, private BuildPersonaContextHandler $persona, private ?KnowledgeRetrieverInterface $knowledge = null) {}

    public function handle(BuildConversationContextCommand $command): ConversationContext
    {
        $messages = $this->messages->findRecentByConversation($command->tenant_id, $command->influencer_id, $command->conversation_id, $command->message_limit);
        $memories = $this->memories->findImportantUserMemories($command->tenant_id, $command->influencer_id, $command->user_id, $command->memory_limit);
        $knowledge = $this->knowledge?->retrieve($command->tenant_id, $command->influencer_id, $command->query_text, $command->knowledge_limit) ?? [];

        return new ConversationContext(
            array_map(fn (Message $message): array => ['role' => $message->sender === 'ai' ? 'assistant' : $message->sender, 'content' => $message->content->value], $messages),
            array_map(fn (Memory $memory): array => ['type' => $memory->type->value, 'content' => $memory->content, 'importance_score' => $memory->importanceScore, 'metadata' => $memory->metadata], $memories),
            $this->persona->handle($command->tenant_id, $command->influencer_id),
            array_map(fn ($item): array => $item->toArray(), $knowledge),
        );
    }
}
