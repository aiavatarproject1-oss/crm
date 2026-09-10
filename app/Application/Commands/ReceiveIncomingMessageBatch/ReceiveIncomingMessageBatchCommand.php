<?php

namespace App\Application\Commands\ReceiveIncomingMessageBatch;

use App\Application\DTO\IncomingMessageBatchData;

final readonly class ReceiveIncomingMessageBatchCommand
{
    public function __construct(public IncomingMessageBatchData $batch) {}
}
