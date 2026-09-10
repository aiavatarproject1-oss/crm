<?php

namespace App\Infrastructure\Messaging\Adapters;

use App\Application\Contracts\PlatformMessageAdapterInterface;
use App\Application\DTO\IncomingPlatformMessageData;
use DateTimeImmutable;

final class InstagramMessageAdapter implements PlatformMessageAdapterInterface
{
    public function normalize(array $payload): IncomingPlatformMessageData
    {
        $entry = $payload['entry'][0] ?? [];
        $event = $entry['messaging'][0] ?? [];
        $message = $event['message'] ?? [];

        return new IncomingPlatformMessageData('instagram', '1.0', (string) ($message['mid'] ?? ''), (string) ($event['sender']['id'] ?? ''), $event['sender']['username'] ?? null, (string) ($message['text'] ?? ''), null, null, ['entry_id' => $entry['id'] ?? null, 'timestamp' => $event['timestamp'] ?? null], $payload, new DateTimeImmutable);
    }
}
