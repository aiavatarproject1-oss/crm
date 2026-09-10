<?php

namespace Tests\Unit\Application;

use App\Application\DTO\IncomingMessageData;
use Error;
use PHPUnit\Framework\TestCase;

class IncomingMessageDataTest extends TestCase
{
    public function test_it_is_immutable(): void
    {
        $data = new IncomingMessageData('tenant-1', 'influencer-1', 'telegram', 'message-1', 'user-1', 'Hello', []);
        $this->expectException(Error::class);

        $data->text = 'Changed';
    }
}
