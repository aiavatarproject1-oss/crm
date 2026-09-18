<?php

namespace App\Infrastructure\Broadcasting\Events;

use App\Domain\Shared\Events\ConversationUpdated;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class ConversationUpdatedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public ConversationUpdated $conversation) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.conversations'),
            new PrivateChannel('admin.conversations.'.(string) $this->conversation->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => (string) $this->conversation->conversationId,
            'tenant_id' => (string) $this->conversation->tenantId,
            'influencer_id' => (string) $this->conversation->influencerId,
            'status' => $this->conversation->status->value,
            'last_activity_at' => $this->conversation->occurredAt->format(DATE_ATOM),
        ];
    }
}
