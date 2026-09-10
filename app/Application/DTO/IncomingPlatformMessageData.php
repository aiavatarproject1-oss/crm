<?php

namespace App\Application\DTO;

use DateTimeImmutable;

final readonly class IncomingPlatformMessageData
{
    public function __construct(
        public string $platform,
        public string $adapter_version,
        public string $external_message_id,
        public string $external_user_id,
        public ?string $username,
        public string $text,
        public ?string $tenant_id,
        public ?string $influencer_id,
        public array $metadata,
        public array $raw_payload,
        public DateTimeImmutable $received_at,
    ) {}

    public function withOwnership(string $tenantId, string $influencerId): self
    {
        return new self($this->platform, $this->adapter_version, $this->external_message_id, $this->external_user_id, $this->username, $this->text, $tenantId, $influencerId, $this->metadata, $this->raw_payload, $this->received_at);
    }

    public function toArray(): array
    {
        return [
            'platform' => $this->platform,
            'adapter_version' => $this->adapter_version,
            'external_message_id' => $this->external_message_id,
            'external_user_id' => $this->external_user_id,
            'username' => $this->username,
            'text' => $this->text,
            'tenant_id' => $this->tenant_id,
            'influencer_id' => $this->influencer_id,
            'metadata' => $this->metadata,
            'raw_payload' => $this->raw_payload,
            'received_at' => $this->received_at->format(DATE_ATOM),
        ];
    }
}
