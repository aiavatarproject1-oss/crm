<?php

namespace App\Domain\Admin\Entities;

use DateTimeImmutable;

/**
 * Immutable audit record of an admin action (who did what to which target, and what changed).
 */
final readonly class AuditLog
{
    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $id,
        public ?string $actorId,
        public ?string $actorUsername,
        public string $action,
        public ?string $targetType,
        public ?string $targetId,
        public array $changes,
        public array $context,
        public ?string $ip,
        public ?string $userAgent,
        public ?string $correlationId,
        public DateTimeImmutable $createdAt,
    ) {}
}
