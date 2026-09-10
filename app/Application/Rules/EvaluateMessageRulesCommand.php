<?php

namespace App\Application\Rules;

use App\Domain\Message\Entities\Message;

final readonly class EvaluateMessageRulesCommand
{
    public function __construct(public Message $message) {}
}
