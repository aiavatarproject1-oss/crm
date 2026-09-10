<?php

namespace App\Infrastructure\Messaging\Adapters;

use App\Application\Contracts\PlatformMessageAdapterInterface;
use App\Application\DTO\IncomingPlatformMessageData;
use DateTimeImmutable;

final class TikTokMessageAdapter implements PlatformMessageAdapterInterface
{
    public function normalize(array $payload): IncomingPlatformMessageData
    {
        $event = $payload['event'] ?? $payload['data'] ?? $payload;

        return new IncomingPlatformMessageData('tiktok', '1.0', (string) ($event['message_id'] ?? $event['id'] ?? ''), (string) ($event['user_id'] ?? $event['from_user_id'] ?? ''), $event['username'] ?? null, (string) ($event['text'] ?? $event['message'] ?? ''), null, null, ['event' => $payload['event_type'] ?? null], $payload, new DateTimeImmutable);
    }
}
