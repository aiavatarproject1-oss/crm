<?php

namespace App\Gateways;

use App\DTO\IncomingMessageDTO;
use App\Gateways\Contracts\MessageGatewayInterface;
use DateTimeImmutable;

final class XGateway implements MessageGatewayInterface
{
    public function receive(array $payload): IncomingMessageDTO
    {
        $createdAt = data_get($payload, 'data.created_at');

        return IncomingMessageDTO::fromArray([
            'platform' => 'x',
            'external_message_id' => data_get($payload, 'data.id'),
            'external_user_id' => data_get($payload, 'data.author_id'),
            'username' => data_get($payload, 'includes.users.0.username'),
            'text' => data_get($payload, 'data.text', ''),
            'language' => data_get($payload, 'data.lang'),
            'metadata' => $payload,
            'received_at' => $createdAt === null ? new DateTimeImmutable : new DateTimeImmutable($createdAt),
        ]);
    }
}
