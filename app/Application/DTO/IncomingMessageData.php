<?php

namespace App\Application\DTO;

final readonly class IncomingMessageData
{
    public function __construct(
        public string $tenant_id,
        public string $influencer_id,
        public string $platform,
        public string $external_message_id,
        public string $external_user_id,
        public string $text,
        public array $metadata,
    ) {}
}
