<?php

namespace App\Repositories\Contracts;

use App\Models\Conversation;

interface ConversationRepositoryInterface
{
    public function find(string $id): ?Conversation;

    public function create(array $attributes): Conversation;
}
