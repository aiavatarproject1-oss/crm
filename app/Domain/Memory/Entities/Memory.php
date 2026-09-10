<?php

namespace App\Domain\Memory\Entities;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\ValueObjects\MemoryId;
use App\Domain\Memory\ValueObjects\MemoryStatus;
use App\Domain\Memory\ValueObjects\MemoryType;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;

/**
 * Long-lived user fact scoped to tenant + influencer + user.
 * Compatible with future batch-based extraction (source_message_batch_id).
 */
final class Memory implements Entity
{
    private function __construct(
        private readonly MemoryId $memoryId,
        public readonly TenantId $tenantId,
        public readonly InfluencerId $influencerId,
        public readonly UserId $userId,
        public readonly MemoryType $type,
        public readonly string $content,
        public readonly float $confidenceScore,
        public readonly float $importanceScore,
        private MemoryStatus $status,
        public readonly ?MessageBatchId $sourceMessageBatchId,
        public array $metadata,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?MemoryId $supersededById = null,
    ) {}

    public static function create(
        MemoryId $memoryId,
        TenantId $tenantId,
        InfluencerId $influencerId,
        UserId $userId,
        MemoryType $type,
        string $content,
        float $confidenceScore,
        float $importanceScore,
        ?MessageBatchId $sourceMessageBatchId = null,
        array $metadata = [],
        ?DateTimeImmutable $occurredAt = null,
    ): self {
        $content = trim($content);
        if ($content === '') {
            throw new DomainException('Memory content cannot be empty.');
        }
        self::assertScore($confidenceScore, 'confidence_score');
        self::assertScore($importanceScore, 'importance_score');

        $occurredAt ??= new DateTimeImmutable;

        return new self(
            $memoryId,
            $tenantId,
            $influencerId,
            $userId,
            $type,
            $content,
            $confidenceScore,
            $importanceScore,
            MemoryStatus::active(),
            $sourceMessageBatchId,
            $metadata,
            $occurredAt,
            $occurredAt,
        );
    }

    /**
     * Hydrate a persisted memory without re-applying create invariants beyond type/status VOs.
     */
    public static function reconstitute(
        MemoryId $memoryId,
        TenantId $tenantId,
        InfluencerId $influencerId,
        UserId $userId,
        MemoryType $type,
        string $content,
        float $confidenceScore,
        float $importanceScore,
        MemoryStatus $status,
        ?MessageBatchId $sourceMessageBatchId,
        array $metadata,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?MemoryId $supersededById = null,
    ): self {
        return new self(
            $memoryId,
            $tenantId,
            $influencerId,
            $userId,
            $type,
            $content,
            $confidenceScore,
            $importanceScore,
            $status,
            $sourceMessageBatchId,
            $metadata,
            $createdAt,
            $updatedAt,
            $supersededById,
        );
    }

    public function id(): MemoryId
    {
        return $this->memoryId;
    }

    public function status(): MemoryStatus
    {
        return $this->status;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function supersededById(): ?MemoryId
    {
        return $this->supersededById;
    }

    public function belongsToScope(TenantId $tenantId, InfluencerId $influencerId, UserId $userId): bool
    {
        return (string) $this->tenantId === (string) $tenantId
            && (string) $this->influencerId === (string) $influencerId
            && (string) $this->userId === (string) $userId;
    }

    public function activate(?DateTimeImmutable $occurredAt = null): void
    {
        if ($this->status->isSuperseded()) {
            throw new DomainException('Superseded memory cannot be activated.');
        }
        if ($this->status->isActive()) {
            return;
        }

        $this->status = MemoryStatus::active();
        $this->touch($occurredAt);
    }

    public function archive(?DateTimeImmutable $occurredAt = null): void
    {
        if ($this->status->isSuperseded()) {
            throw new DomainException('Superseded memory cannot be archived.');
        }
        if ($this->status->isArchived()) {
            return;
        }

        $this->status = MemoryStatus::archived();
        $this->touch($occurredAt);
    }

    public function supersede(?MemoryId $successorId = null, ?DateTimeImmutable $occurredAt = null): void
    {
        if ($this->status->isSuperseded()) {
            return;
        }

        $this->status = MemoryStatus::superseded();
        $this->supersededById = $successorId;
        if ($successorId !== null) {
            $this->metadata['superseded_by'] = (string) $successorId;
        }
        $this->touch($occurredAt);
    }

    private function touch(?DateTimeImmutable $occurredAt): void
    {
        $this->updatedAt = $occurredAt ?? new DateTimeImmutable;
    }

    private static function assertScore(float $score, string $field): void
    {
        if ($score < 0.0 || $score > 1.0) {
            throw new DomainException("Memory {$field} must be between 0 and 1.");
        }
    }
}
