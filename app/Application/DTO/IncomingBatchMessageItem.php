<?php

namespace App\Application\DTO;

use DateTimeImmutable;

/**
 * One buffered message inside an inbound conversation turn.
 *
 * @param  list<array{url: string, type?: string, mime_type?: string}>  $media
 */
final readonly class IncomingBatchMessageItem
{
    public function __construct(
        public string $external_message_id,
        public string $text,
        public DateTimeImmutable $received_at,
        public array $metadata = [],
        public array $raw_payload = [],
        public string $content_type = 'text',
        public array $media = [],
    ) {}
}
