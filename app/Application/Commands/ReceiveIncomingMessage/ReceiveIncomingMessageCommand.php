<?php

namespace App\Application\Commands\ReceiveIncomingMessage;

use App\Application\DTO\IncomingPlatformMessageData;

final readonly class ReceiveIncomingMessageCommand
{
    public function __construct(public IncomingPlatformMessageData $message) {}
}
