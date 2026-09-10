<?php

namespace App\Gateways;

use App\DTO\IncomingMessageDTO;
use App\Gateways\Contracts\MessageGatewayInterface;
use DateTimeImmutable;

final class TikTokGateway implements MessageGatewayInterface
{
    public function receive(array $payload): IncomingMessageDTO
    {
        return IncomingMessageDTO::fromArray([
            'platform' => 'tiktok',
            'external_message_id' => data_get($payload, 'data.message_id', data_get($payload, 'event')),
            'external_user_id' => data_get($payload, 'user.open_id'),
            'username' => data_get($payload, 'user.display_name'),
            'text' => data_get($payload, 'data.content', data_get($payload, 'data.text', '')),
            'language' => data_get($payload, 'data.language'),
            'metadata' => $payload,
            'received_at' => (new DateTimeImmutable)->setTimestamp((int) data_get($payload, 'create_time', time())),
        ]);
    }
}
