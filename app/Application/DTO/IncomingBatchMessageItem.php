<?php

namespace App\Application\DTO;

use DateTimeImmutable;

/**
 * One buffered message inside an inbound conversation turn.
 */
final readonly class IncomingBatchMessageItem
{
    public function __construct(
        public string $external_message_id,
        public string $text,
        public DateTimeImmutable $received_at,
        public array $metadata = [],
        public array $raw_payload = [],
    ) {}
}
