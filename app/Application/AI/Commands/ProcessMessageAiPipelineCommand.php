<?php

namespace App\Application\AI\Commands;

use App\Domain\Message\Entities\Message;
use App\Domain\User\ValueObjects\UserId;

final readonly class ProcessMessageAiPipelineCommand
{
    public function __construct(public Message $message, public UserId $user_id, public array $metadata = []) {}
}
