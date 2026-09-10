<?php

namespace App\Infrastructure\Messaging;

use App\Application\Contracts\PlatformMessageAdapterInterface;
use App\Infrastructure\Messaging\Adapters\InstagramMessageAdapter;
use App\Infrastructure\Messaging\Adapters\RedditMessageAdapter;
use App\Infrastructure\Messaging\Adapters\TelegramMessageAdapter;
use App\Infrastructure\Messaging\Adapters\TikTokMessageAdapter;
use App\Infrastructure\Messaging\Adapters\XMessageAdapter;
use InvalidArgumentException;

final readonly class PlatformResolver
{
    public function __construct(private TelegramMessageAdapter $telegram, private InstagramMessageAdapter $instagram, private XMessageAdapter $x, private RedditMessageAdapter $reddit, private TikTokMessageAdapter $tiktok) {}

    public function resolve(string $platform): PlatformMessageAdapterInterface
    {
        return match (strtolower(trim($platform))) {
            'telegram' => $this->telegram, 'instagram' => $this->instagram, 'x' => $this->x, 'reddit' => $this->reddit, 'tiktok' => $this->tiktok,
            default => throw new InvalidArgumentException("Unsupported platform [$platform]."),
        };
    }
}
