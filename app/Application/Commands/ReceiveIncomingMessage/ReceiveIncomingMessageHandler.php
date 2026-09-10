<?php

namespace App\Application\Commands\ReceiveIncomingMessage;

use App\Application\Commands\ReceiveIncomingMessageBatch\ReceiveIncomingMessageBatchCommand;
use App\Application\Commands\ReceiveIncomingMessageBatch\ReceiveIncomingMessageBatchHandler;
use App\Application\DTO\IncomingMessageBatchData;
use App\Application\DTO\MessageIngestionResult;

/**
 * Compatibility wrapper: single platform message → one-item batch ingestion.
 */
final readonly class ReceiveIncomingMessageHandler
{
    public function __construct(private ReceiveIncomingMessageBatchHandler $batches) {}

    public function handle(ReceiveIncomingMessageCommand $command): MessageIngestionResult
    {
        $result = $this->batches->handle(new ReceiveIncomingMessageBatchCommand(
            IncomingMessageBatchData::fromPlatformMessage($command->message),
        ));

        return $result->firstMessageResult();
    }
}
