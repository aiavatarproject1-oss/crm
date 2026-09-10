<?php

namespace App\Application\DTO;

final readonly class MessageIngestionResult
{
    public function __construct(public string $message_id, public string $conversation_id, public string $user_id, public bool $created, public bool $duplicate) {}

    public function toArray(): array
    {
        return ['message_id' => $this->message_id, 'conversation_id' => $this->conversation_id, 'user_id' => $this->user_id, 'created' => $this->created, 'duplicate' => $this->duplicate];
    }
}
