<?php

namespace App\Application\Quality\DTO;

final readonly class QualityCheckRecord
{
    /**
     * @param  list<string|mixed>  $issues
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $characterId,
        public string $conversationId,
        public ?string $userMessageId,
        public string $userMessage,
        public string $aiResponse,
        public float $score,
        public float $threshold,
        public bool $approved,
        public array $issues,
        public string $reason,
        public array $metadata = [],
        public ?string $createdAt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'character_id' => $this->characterId,
            'conversation_id' => $this->conversationId,
            'user_message_id' => $this->userMessageId,
            'user_message' => $this->userMessage,
            'ai_response' => $this->aiResponse,
            'score' => $this->score,
            'threshold' => $this->threshold,
            'approved' => $this->approved,
            'issues' => array_values($this->issues),
            'reason' => $this->reason,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
        ];
    }
}
