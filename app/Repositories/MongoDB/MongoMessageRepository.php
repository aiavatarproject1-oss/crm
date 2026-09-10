<?php

namespace App\Repositories\MongoDB;

use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class MongoMessageRepository implements MessageRepositoryInterface
{
    public function find(string $id): ?Message
    {
        return Message::query()->find($id);
    }

    public function forConversation(string $conversationId): Collection
    {
        return Message::query()
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get();
    }

    public function create(array $attributes): Message
    {
        return Message::query()->create($attributes);
    }
}
