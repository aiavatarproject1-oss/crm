<?php

namespace App\Domain\Knowledge\Entities;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceId;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceStatus;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceType;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;

final class KnowledgeSource implements Entity
{
    private function __construct(
        private readonly KnowledgeSourceId $sourceId,
        public readonly TenantId $tenantId,
        public readonly InfluencerId $influencerId,
        public readonly KnowledgeSourceType $type,
        public readonly string $name,
        public readonly string $checksum,
        public readonly string $payload,
        private KnowledgeSourceStatus $status,
        public array $metadata,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        KnowledgeSourceId $sourceId,
        TenantId $tenantId,
        InfluencerId $influencerId,
        KnowledgeSourceType $type,
        string $name,
        string $payload,
        ?string $checksum = null,
        array $metadata = [],
        ?DateTimeImmutable $occurredAt = null,
    ): self {
        $name = trim($name);
        if ($name === '') {
            throw new DomainException('Knowledge source name cannot be empty.');
        }

        $occurredAt ??= new DateTimeImmutable;
        $checksum ??= hash('sha256', $payload);

        return new self(
            $sourceId,
            $tenantId,
            $influencerId,
            $type,
            $name,
            $checksum,
            $payload,
            KnowledgeSourceStatus::pending(),
            $metadata,
            $occurredAt,
            $occurredAt,
        );
    }

    public static function reconstitute(
        KnowledgeSourceId $sourceId,
        TenantId $tenantId,
        InfluencerId $influencerId,
        KnowledgeSourceType $type,
        string $name,
        string $checksum,
        string $payload,
        KnowledgeSourceStatus $status,
        array $metadata,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self($sourceId, $tenantId, $influencerId, $type, $name, $checksum, $payload, $status, $metadata, $createdAt, $updatedAt);
    }

    public function id(): KnowledgeSourceId
    {
        return $this->sourceId;
    }

    public function status(): KnowledgeSourceStatus
    {
        return $this->status;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function markParsed(?DateTimeImmutable $occurredAt = null): void
    {
        $this->status = KnowledgeSourceStatus::parsed();
        $this->touch($occurredAt);
    }

    public function markIngested(?DateTimeImmutable $occurredAt = null): void
    {
        $this->status = KnowledgeSourceStatus::ingested();
        $this->touch($occurredAt);
    }

    public function markFailed(string $reason, ?DateTimeImmutable $occurredAt = null): void
    {
        $this->status = KnowledgeSourceStatus::failed();
        $this->metadata['failure_reason'] = $reason;
        $this->touch($occurredAt);
    }

    private function touch(?DateTimeImmutable $occurredAt): void
    {
        $this->updatedAt = $occurredAt ?? new DateTimeImmutable;
    }
}
