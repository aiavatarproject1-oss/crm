<?php

namespace App\Infrastructure\Broadcasting\Events;

use App\Domain\Shared\Events\MessageCreated;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Live feed of every stored message (user + AI) for the admin conversation monitor.
 * Channels: `admin.conversations` (list view) and `admin.conversations.{id}` (detail view).
 */
final class ConversationMessageBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public MessageCreated $message) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.conversations'),
            new PrivateChannel('admin.conversations.'.(string) $this->message->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $metadata = $this->message->metadata;
        unset($metadata['images'], $metadata['base64']);

        return [
            'id' => (string) $this->message->messageId,
            'tenant_id' => (string) $this->message->tenantId,
            'influencer_id' => (string) $this->message->influencerId,
            'conversation_id' => (string) $this->message->conversationId,
            'sender' => $this->message->sender,
            'direction' => $this->message->direction,
            'content_type' => $this->message->contentType,
            'content' => $this->message->content->value,
            'platform' => $this->message->platform->value,
            'external_message_id' => (string) $this->message->externalMessageId,
            'metadata' => $metadata,
            'created_at' => $this->message->occurredAt->format(DATE_ATOM),
        ];
    }
}
