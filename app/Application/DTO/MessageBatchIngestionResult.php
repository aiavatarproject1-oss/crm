<?php

namespace App\Application\DTO;

final readonly class MessageBatchIngestionResult
{
    /**
     * @param  list<string>  $message_ids
     */
    public function __construct(
        public string $batch_id,
        public string $conversation_id,
        public string $user_id,
        public int $message_count,
        public int $created_count,
        public int $duplicate_count,
        public array $message_ids,
        public bool $created,
        public bool $duplicate,
        public string $task_id = '',
    ) {}

    public function toArray(): array
    {
        return [
            'batch_id' => $this->batch_id,
            'message_batch_id' => $this->batch_id,
            'conversation_id' => $this->conversation_id,
            'user_id' => $this->user_id,
            'task_id' => $this->task_id,
            'message_count' => $this->message_count,
            'created_count' => $this->created_count,
            'duplicate_count' => $this->duplicate_count,
            'message_ids' => $this->message_ids,
            'created' => $this->created,
            'duplicate' => $this->duplicate,
        ];
    }

    public function firstMessageResult(): MessageIngestionResult
    {
        $messageId = $this->message_ids[0] ?? '';

        return new MessageIngestionResult(
            $messageId,
            $this->conversation_id,
            $this->user_id,
            $this->created_count > 0,
            $this->duplicate && $this->created_count === 0,
        );
    }
}
