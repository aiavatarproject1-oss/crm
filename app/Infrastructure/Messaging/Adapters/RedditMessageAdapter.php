<?php

namespace App\Infrastructure\Messaging\Adapters;

use App\Application\Contracts\PlatformMessageAdapterInterface;
use App\Application\DTO\IncomingPlatformMessageData;
use DateTimeImmutable;

final class RedditMessageAdapter implements PlatformMessageAdapterInterface
{
    public function normalize(array $payload): IncomingPlatformMessageData
    {
        $data = $payload['data'] ?? $payload;
        $author = (string) ($data['author_fullname'] ?? $data['author'] ?? '');

        return new IncomingPlatformMessageData('reddit', '1.0', (string) ($data['id'] ?? $data['name'] ?? ''), $author, $data['author'] ?? null, (string) ($data['body'] ?? $data['selftext'] ?? ''), null, null, ['subreddit' => $data['subreddit'] ?? null], $payload, new DateTimeImmutable);
    }
}
