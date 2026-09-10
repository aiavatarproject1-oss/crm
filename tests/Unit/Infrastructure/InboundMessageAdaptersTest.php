<?php

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\Messaging\Adapters\InstagramMessageAdapter;
use App\Infrastructure\Messaging\Adapters\TelegramMessageAdapter;
use PHPUnit\Framework\TestCase;

final class InboundMessageAdaptersTest extends TestCase
{
    public function test_telegram_payload_is_normalized_and_preserved(): void
    {
        $payload = ['update_id' => 42, 'message' => ['message_id' => 101, 'from' => ['id' => 202, 'username' => 'amir'], 'chat' => ['id' => 303], 'text' => 'Hello']];
        $data = (new TelegramMessageAdapter)->normalize($payload);
        self::assertSame('telegram', $data->platform);
        self::assertSame('101', $data->external_message_id);
        self::assertSame('202', $data->external_user_id);
        self::assertSame('amir', $data->username);
        self::assertSame('Hello', $data->text);
        self::assertSame($payload, $data->raw_payload);
    }

    public function test_instagram_payload_is_normalized(): void
    {
        $payload = ['entry' => [['id' => 'page-1', 'messaging' => [['sender' => ['id' => 'user-1'], 'timestamp' => 123, 'message' => ['mid' => 'message-1', 'text' => 'Hi']]]]]];
        $data = (new InstagramMessageAdapter)->normalize($payload);
        self::assertSame('instagram', $data->platform);
        self::assertSame('message-1', $data->external_message_id);
        self::assertSame('user-1', $data->external_user_id);
        self::assertSame('Hi', $data->text);
        self::assertSame($payload, $data->raw_payload);
    }
}
