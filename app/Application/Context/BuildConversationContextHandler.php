<?php

namespace App\Application\Context;

use App\Application\Character\Contracts\CharacterSettingsRepositoryInterface;
use App\Application\Character\DTO\CharacterSettingsData;
use App\Application\Context\DTO\ConversationContext;
use App\Application\Context\Services\SalesFunnelStageResolver;
use App\Application\Contracts\MemoryRepositoryInterface;
use App\Application\Contracts\MessageRepositoryInterface;
use App\Application\RAG\Contracts\KnowledgeRetrieverInterface;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Message\Entities\Message;

final readonly class BuildConversationContextHandler
{
    public function __construct(
        private MessageRepositoryInterface $messages,
        private MemoryRepositoryInterface $memories,
        private BuildPersonaContextHandler $persona,
        private ?KnowledgeRetrieverInterface $knowledge = null,
        private ?SalesFunnelStageResolver $salesFunnel = null,
        private bool $ragEnabled = true,
        private ?CharacterSettingsRepositoryInterface $characters = null,
    ) {}

    public function handle(BuildConversationContextCommand $command): ConversationContext
    {
        $character = $this->resolveCharacter((string) $command->tenant_id, (string) $command->influencer_id);
        $ragOn = $this->ragEnabled && ($character === null || ($character->featureFlags['rag'] ?? true));

        $messages = $this->messages->findRecentByConversation($command->tenant_id, $command->influencer_id, $command->conversation_id, $command->message_limit);
        $memories = $this->memories->findImportantUserMemories($command->tenant_id, $command->influencer_id, $command->user_id, $command->memory_limit);
        $knowledge = ($ragOn && $this->knowledge !== null)
            ? $this->knowledge->retrieve($command->tenant_id, $command->influencer_id, $command->query_text, $command->knowledge_limit)
            : [];

        $recent = array_map(fn (Message $message): array => [
            'role' => $message->sender === 'ai' ? 'assistant' : $message->sender,
            'content' => $message->content->value,
        ], $messages);

        $resolver = $this->salesFunnelFor($character);
        $stage = $resolver->resolve($recent, $command->query_text);

        return new ConversationContext(
            $recent,
            array_map(fn (Memory $memory): array => [
                'type' => $memory->type->value,
                'content' => $memory->content,
                'importance_score' => $memory->importanceScore,
                'metadata' => $memory->metadata,
            ], $memories),
            $this->persona->handle($command->tenant_id, $command->influencer_id),
            array_map(fn ($item): array => $item->toArray(), $knowledge),
            $stage,
            $character,
        );
    }

    private function resolveCharacter(string $tenantId, string $characterId): ?CharacterSettingsData
    {
        if ($this->characters === null) {
            return null;
        }

        return $this->characters->findByCharacter($tenantId, $characterId)
            ?? $this->characters->find($characterId)
            ?? $this->characters->findBySlug($characterId);
    }

    private function salesFunnelFor(?CharacterSettingsData $character): SalesFunnelStageResolver
    {
        if ($character === null) {
            return $this->salesFunnel ?? new SalesFunnelStageResolver;
        }

        $stages = (array) ($character->salesFunnel['stages'] ?? []);
        $warmup = 4;
        $tease = 8;
        foreach ($stages as $stage) {
            if (! is_array($stage)) {
                continue;
            }
            $key = (string) ($stage['key'] ?? '');
            $min = (int) ($stage['min_messages'] ?? 0);
            if ($key === 'tease') {
                $warmup = max(0, $min - 1);
            }
            if ($key === 'sell') {
                $tease = max($warmup + 1, $min - 1);
            }
        }

        return new SalesFunnelStageResolver($warmup, $tease);
    }
}
