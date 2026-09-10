<?php

namespace App\Domain\Memory\Entities;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\ValueObjects\MemoryCandidateId;
use App\Domain\Memory\ValueObjects\MemoryCandidateStatus;
use App\Domain\Memory\ValueObjects\MemoryType;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;

/**
 * Proposed memory derived from a conversation turn. Not durable user Memory until approved + persisted later.
 */
final class MemoryCandidate implements Entity
{
    private function __construct(
        private readonly MemoryCandidateId $memoryCandidateId,
        public readonly TenantId $tenantId,
        public readonly InfluencerId $influencerId,
        public readonly UserId $userId,
        public readonly MemoryType $type,
        public readonly string $content,
        public readonly float $confidenceScore,
        public readonly float $importanceScore,
        public readonly MessageBatchId $sourceMessageBatchId,
        private MemoryCandidateStatus $status,
        public array $metadata,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?string $rejectionReason = null,
    ) {}

    public static function create(
        MemoryCandidateId $memoryCandidateId,
        TenantId $tenantId,
        InfluencerId $influencerId,
        UserId $userId,
        MemoryType $type,
        string $content,
        float $confidenceScore,
        float $importanceScore,
        MessageBatchId $sourceMessageBatchId,
        array $metadata = [],
        ?DateTimeImmutable $occurredAt = null,
    ): self {
        self::assertScore($confidenceScore, 'confidence_score');
        self::assertScore($importanceScore, 'importance_score');

        $occurredAt ??= new DateTimeImmutable;

        return new self(
            $memoryCandidateId,
            $tenantId,
            $influencerId,
            $userId,
            $type,
            $content,
            $confidenceScore,
            $importanceScore,
            $sourceMessageBatchId,
            MemoryCandidateStatus::pending(),
            $metadata,
            $occurredAt,
            $occurredAt,
        );
    }

    public static function reconstitute(
        MemoryCandidateId $memoryCandidateId,
        TenantId $tenantId,
        InfluencerId $influencerId,
        UserId $userId,
        MemoryType $type,
        string $content,
        float $confidenceScore,
        float $importanceScore,
        MessageBatchId $sourceMessageBatchId,
        MemoryCandidateStatus $status,
        array $metadata,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        ?string $rejectionReason = null,
    ): self {
        return new self(
            $memoryCandidateId,
            $tenantId,
            $influencerId,
            $userId,
            $type,
            $content,
            $confidenceScore,
            $importanceScore,
            $sourceMessageBatchId,
            $status,
            $metadata,
            $createdAt,
            $updatedAt,
            $rejectionReason,
        );
    }

    public function id(): MemoryCandidateId
    {
        return $this->memoryCandidateId;
    }

    public function status(): MemoryCandidateStatus
    {
        return $this->status;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function rejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function belongsToScope(TenantId $tenantId, InfluencerId $influencerId, UserId $userId): bool
    {
        return (string) $this->tenantId === (string) $tenantId
            && (string) $this->influencerId === (string) $influencerId
            && (string) $this->userId === (string) $userId;
    }

    public function approve(?DateTimeImmutable $occurredAt = null): void
    {
        if ($this->status->isApproved()) {
            return;
        }
        if (! $this->status->isPending()) {
            throw new DomainException('Only pending memory candidates can be approved.');
        }

        $this->status = MemoryCandidateStatus::approved();
        $this->rejectionReason = null;
        $this->touch($occurredAt);
    }

    public function reject(string $reason, ?DateTimeImmutable $occurredAt = null): void
    {
        if ($this->status->isRejected()) {
            return;
        }
        if (! $this->status->isPending()) {
            throw new DomainException('Only pending memory candidates can be rejected.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Rejection reason cannot be empty.');
        }

        $this->status = MemoryCandidateStatus::rejected();
        $this->rejectionReason = $reason;
        $this->metadata['rejection_reason'] = $reason;
        $this->touch($occurredAt);
    }

    private function touch(?DateTimeImmutable $occurredAt): void
    {
        $this->updatedAt = $occurredAt ?? new DateTimeImmutable;
    }

    private static function assertScore(float $score, string $field): void
    {
        if ($score < 0.0 || $score > 1.0) {
            throw new DomainException("Memory candidate {$field} must be between 0 and 1.");
        }
    }
}
