<?php

namespace App\Application\Contracts;

use App\Application\DTO\IncomingPlatformMessageData;

interface PlatformMessageAdapterInterface
{
    public function normalize(array $payload): IncomingPlatformMessageData;
}
