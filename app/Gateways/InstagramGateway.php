<?php

namespace App\Gateways;

use App\DTO\IncomingMessageDTO;
use App\Gateways\Contracts\MessageGatewayInterface;
use DateTimeImmutable;

final class InstagramGateway implements MessageGatewayInterface
{
    public function receive(array $payload): IncomingMessageDTO
    {
        $message = data_get($payload, 'entry.0.messaging.0', []);
        $timestamp = data_get($message, 'timestamp');

        return IncomingMessageDTO::fromArray([
            'platform' => 'instagram',
            'external_message_id' => data_get($message, 'message.mid'),
            'external_user_id' => data_get($message, 'sender.id'),
            'username' => data_get($message, 'sender.username'),
            'text' => data_get($message, 'message.text', ''),
            'language' => data_get($message, 'message.language'),
            'metadata' => $payload,
            'received_at' => $timestamp === null
                ? new DateTimeImmutable
                : (new DateTimeImmutable)->setTimestamp(intdiv((int) $timestamp, 1000)),
        ]);
    }
}
