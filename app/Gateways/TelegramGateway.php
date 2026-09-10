<?php

namespace App\Gateways;

use App\DTO\IncomingMessageDTO;
use App\Gateways\Contracts\MessageGatewayInterface;
use DateTimeImmutable;

final class TelegramGateway implements MessageGatewayInterface
{
    public function receive(array $payload): IncomingMessageDTO
    {
        $message = $payload['message'] ?? $payload['edited_message'] ?? [
            'message_id' => $payload['external_message_id'] ?? null,
            'from' => [
                'id' => $payload['external_user_id'] ?? null,
                'username' => $payload['username'] ?? null,
                'language_code' => $payload['language'] ?? null,
            ],
            'text' => $payload['text'] ?? '',
            'date' => time(),
        ];

        return IncomingMessageDTO::fromArray([
            'platform' => 'telegram',
            'external_message_id' => data_get($message, 'message_id'),
            'external_user_id' => data_get($message, 'from.id'),
            'username' => data_get($message, 'from.username'),
            'text' => data_get($message, 'text', ''),
            'language' => data_get($message, 'from.language_code'),
            'metadata' => $payload,
            'received_at' => (new DateTimeImmutable)->setTimestamp((int) data_get($message, 'date', time())),
        ]);
    }
}
