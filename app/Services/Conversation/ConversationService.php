<?php

namespace App\Services\Conversation;

use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;

class ConversationService
{
    public function __construct(
        protected ConversationRepositoryInterface $conversations,
        protected MessageRepositoryInterface $messages,
    ) {}
}
