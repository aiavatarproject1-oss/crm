<?php

namespace App\Gateways;

use App\DTO\IncomingMessageDTO;
use App\Gateways\Contracts\MessageGatewayInterface;
use DateTimeImmutable;

final class RedditGateway implements MessageGatewayInterface
{
    public function receive(array $payload): IncomingMessageDTO
    {
        return IncomingMessageDTO::fromArray([
            'platform' => 'reddit',
            'external_message_id' => data_get($payload, 'id'),
            'external_user_id' => data_get($payload, 'author_fullname', data_get($payload, 'author')),
            'username' => data_get($payload, 'author'),
            'text' => data_get($payload, 'body', data_get($payload, 'text', '')),
            'language' => data_get($payload, 'language'),
            'metadata' => $payload,
            'received_at' => (new DateTimeImmutable)->setTimestamp((int) data_get($payload, 'created_utc', time())),
        ]);
    }
}
