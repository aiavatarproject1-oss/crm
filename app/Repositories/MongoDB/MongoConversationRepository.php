<?php

namespace App\Repositories\MongoDB;

use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;

class MongoConversationRepository implements ConversationRepositoryInterface
{
    public function find(string $id): ?Conversation
    {
        return Conversation::query()->find($id);
    }

    public function create(array $attributes): Conversation
    {
        return Conversation::query()->create($attributes);
    }
}
