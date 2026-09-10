<?php

namespace Tests\Unit\Gateways;

use App\DTO\IncomingMessageDTO;
use App\Gateways\TelegramGateway;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TelegramGatewayTest extends TestCase
{
    public function test_it_converts_a_telegram_payload_to_an_incoming_message_dto(): void
    {
        $payload = [
            'update_id' => 123456,
            'message' => [
                'message_id' => 42,
                'date' => 1_725_184_000,
                'from' => [
                    'id' => 998877,
                    'username' => 'sofia_fan',
                    'language_code' => 'en',
                ],
                'text' => 'Hello Sofia',
            ],
        ];

        $message = (new TelegramGateway)->receive($payload);

        $this->assertInstanceOf(IncomingMessageDTO::class, $message);
        $this->assertSame('telegram', $message->platform);
        $this->assertSame('42', $message->external_message_id);
        $this->assertSame('998877', $message->external_user_id);
        $this->assertSame('sofia_fan', $message->username);
        $this->assertSame('Hello Sofia', $message->text);
        $this->assertSame('en', $message->language);
        $this->assertSame($payload, $message->metadata);
        $this->assertEquals((new DateTimeImmutable)->setTimestamp(1_725_184_000), $message->received_at);
    }
}
