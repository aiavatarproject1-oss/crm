<?php

namespace App\Infrastructure\Messaging\Adapters;

use App\Application\Contracts\PlatformMessageAdapterInterface;
use App\Application\DTO\IncomingPlatformMessageData;
use DateTimeImmutable;

final class TelegramMessageAdapter implements PlatformMessageAdapterInterface
{
    public function normalize(array $payload): IncomingPlatformMessageData
    {
        $message = $payload['message'] ?? $payload['edited_message'] ?? [];
        $user = $message['from'] ?? [];

        return new IncomingPlatformMessageData('telegram', '1.0', (string) ($message['message_id'] ?? ''), (string) ($user['id'] ?? ''), $user['username'] ?? null, (string) ($message['text'] ?? ''), null, null, ['update_id' => $payload['update_id'] ?? null, 'chat_id' => $message['chat']['id'] ?? null], $payload, new DateTimeImmutable);
    }
}
