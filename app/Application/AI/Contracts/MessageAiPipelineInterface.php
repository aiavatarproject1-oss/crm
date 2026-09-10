<?php

namespace App\Application\AI\Contracts;

use App\Domain\Message\Entities\Message;
use App\Domain\User\ValueObjects\UserId;

interface MessageAiPipelineInterface
{
    public function process(Message $message, UserId $userId): void;
}
