<?php

namespace App\Repositories\Contracts;

use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;

interface MessageRepositoryInterface
{
    public function find(string $id): ?Message;

    public function forConversation(string $conversationId): Collection;

    public function create(array $attributes): Message;
}
