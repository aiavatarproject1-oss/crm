<?php

namespace App\Application\DTO;

/**
 * Bot-buffered conversation turn: shared ownership + ordered messages[].
 */
final readonly class IncomingMessageBatchData
{
    /**
     * @param  list<IncomingBatchMessageItem>  $messages
     */
    public function __construct(
        public string $platform,
        public string $external_user_id,
        public ?string $username,
        public ?string $tenant_id,
        public ?string $influencer_id,
        public array $messages,
        public array $metadata = [],
        public string $adapter_version = 'batch-1.0',
    ) {}

    public static function fromPlatformMessage(IncomingPlatformMessageData $message): self
    {
        return new self(
            $message->platform,
            $message->external_user_id,
            $message->username,
            $message->tenant_id,
            $message->influencer_id,
            [
                new IncomingBatchMessageItem(
                    $message->external_message_id,
                    $message->text,
                    $message->received_at,
                    $message->metadata,
                    $message->raw_payload,
                    'text',
                    [],
                ),
            ],
            ['source' => 'single_platform_payload'],
            $message->adapter_version,
        );
    }

    public function withOwnership(string $tenantId, string $influencerId): self
    {
        return new self(
            $this->platform,
            $this->external_user_id,
            $this->username,
            $tenantId,
            $influencerId,
            $this->messages,
            $this->metadata,
            $this->adapter_version,
        );
    }

    public function toArray(): array
    {
        return [
            'platform' => $this->platform,
            'external_user_id' => $this->external_user_id,
            'username' => $this->username,
            'tenant_id' => $this->tenant_id,
            'influencer_id' => $this->influencer_id,
            'adapter_version' => $this->adapter_version,
            'metadata' => $this->metadata,
            'messages' => array_map(
                static fn (IncomingBatchMessageItem $item): array => [
                    'external_message_id' => $item->external_message_id,
                    'text' => $item->text,
                    'content_type' => $item->content_type,
                    'media' => $item->media,
                    'received_at' => $item->received_at->format(DATE_ATOM),
                    'metadata' => $item->metadata,
                    'raw_payload' => $item->raw_payload,
                ],
                $this->messages,
            ),
        ];
    }
}
