<?php

namespace App\Infrastructure\Messaging\Adapters;

use App\Application\Contracts\PlatformMessageAdapterInterface;
use App\Application\DTO\IncomingPlatformMessageData;
use DateTimeImmutable;

final class XMessageAdapter implements PlatformMessageAdapterInterface
{
    public function normalize(array $payload): IncomingPlatformMessageData
    {
        $data = $payload['data'] ?? [];
        $authorId = (string) ($data['author_id'] ?? '');
        $users = $payload['includes']['users'] ?? [];
        $author = current(array_filter($users, fn (array $user): bool => (string) ($user['id'] ?? '') === $authorId)) ?: [];

        return new IncomingPlatformMessageData('x', '1.0', (string) ($data['id'] ?? ''), $authorId, $author['username'] ?? null, (string) ($data['text'] ?? ''), null, null, ['conversation_id' => $data['conversation_id'] ?? null], $payload, new DateTimeImmutable);
    }
}
