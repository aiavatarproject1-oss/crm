<?php

namespace Tests\Unit\Application;

use App\Application\Commands\ReceiveIncomingMessage\ReceiveIncomingMessageCommand;
use App\Application\DTO\IncomingPlatformMessageData;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ReceiveIncomingMessageCommandTest extends TestCase
{
    public function test_it_carries_immutable_incoming_message_data(): void
    {
        $data = new IncomingPlatformMessageData('telegram', '1.0', 'message-1', 'user-1', 'amir', 'Hello', 'tenant-1', 'influencer-1', ['language' => 'en'], [], new DateTimeImmutable);
        $command = new ReceiveIncomingMessageCommand($data);

        $this->assertSame($data, $command->message);
        $this->assertSame('tenant-1', $command->message->tenant_id);
        $this->assertSame('message-1', $command->message->external_message_id);
    }
}
