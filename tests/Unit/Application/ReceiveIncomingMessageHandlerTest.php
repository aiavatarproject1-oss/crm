<?php

namespace Tests\Unit\Application;

use App\Application\Commands\ReceiveIncomingMessage\ReceiveIncomingMessageHandler;
use App\Application\Commands\ReceiveIncomingMessageBatch\ReceiveIncomingMessageBatchHandler;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ReceiveIncomingMessageHandlerTest extends TestCase
{
    public function test_it_delegates_to_batch_ingestion_handler(): void
    {
        $constructor = (new ReflectionClass(ReceiveIncomingMessageHandler::class))->getConstructor();
        $parameters = $constructor?->getParameters() ?? [];

        $this->assertCount(1, $parameters);
        $this->assertSame(ReceiveIncomingMessageBatchHandler::class, $parameters[0]->getType()?->getName());
    }
}
