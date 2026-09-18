<?php

namespace App\Domain\AI\Entities;

use App\Domain\AI\ValueObjects\AiProcessingTaskId;
use App\Domain\AI\ValueObjects\AiProcessingTaskStatus;
use App\Domain\Conversation\ValueObjects\ConversationId;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Shared\Contracts\Entity;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;

final class AiProcessingTask implements Entity
{
    private function __construct(
        private readonly AiProcessingTaskId $taskId,
        public readonly TenantId $tenantId,
        public readonly InfluencerId $influencerId,
        public readonly ConversationId $conversationId,
        public readonly MessageBatchId $messageBatchId,
        private AiProcessingTaskStatus $status,
        private int $attempts,
        private ?string $error,
        private ?DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $finishedAt,
        private array $metadata,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        AiProcessingTaskId $taskId,
        TenantId $tenantId,
        InfluencerId $influencerId,
        ConversationId $conversationId,
        MessageBatchId $messageBatchId,
        array $metadata = [],
    ): self {
        return new self(
            $taskId,
            $tenantId,
            $influencerId,
            $conversationId,
            $messageBatchId,
            AiProcessingTaskStatus::pending(),
            0,
            null,
            null,
            null,
            $metadata,
        );
    }

    public static function reconstitute(
        AiProcessingTaskId $taskId,
        TenantId $tenantId,
        InfluencerId $influencerId,
        ConversationId $conversationId,
        MessageBatchId $messageBatchId,
        AiProcessingTaskStatus $status,
        int $attempts,
        ?string $error,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $finishedAt,
        array $metadata = [],
    ): self {
        if ($attempts < 0) {
            throw new DomainException('AI processing task attempts cannot be negative.');
        }

        return new self(
            $taskId,
            $tenantId,
            $influencerId,
            $conversationId,
            $messageBatchId,
            $status,
            $attempts,
            $error,
            $startedAt,
            $finishedAt,
            $metadata,
        );
    }

    public function id(): AiProcessingTaskId
    {
        return $this->taskId;
    }

    public function status(): AiProcessingTaskStatus
    {
        return $this->status;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function mergeMetadata(array $extra): void
    {
        $this->metadata = array_replace($this->metadata, $extra);
    }

    public function markProcessing(?DateTimeImmutable $at = null): void
    {
        if ($this->status->isCompleted()) {
            throw new DomainException('Completed AI processing tasks cannot be reprocessed.');
        }

        $this->status = AiProcessingTaskStatus::processing();
        $this->attempts++;
        $this->startedAt ??= $at ?? new DateTimeImmutable;
        $this->error = null;
        $this->finishedAt = null;
    }

    public function markCompleted(?DateTimeImmutable $at = null): void
    {
        $this->status = AiProcessingTaskStatus::completed();
        $this->error = null;
        $this->finishedAt = $at ?? new DateTimeImmutable;
    }

    public function markFailed(string $error, ?DateTimeImmutable $at = null): void
    {
        $this->status = AiProcessingTaskStatus::failed();
        $this->error = $this->normalizeError($error);
        $this->finishedAt = $at ?? new DateTimeImmutable;
    }

    public function markRetrying(string $error): void
    {
        $this->status = AiProcessingTaskStatus::retrying();
        $this->error = $this->normalizeError($error);
        $this->finishedAt = null;
    }

    private function normalizeError(string $error): string
    {
        $trimmed = trim($error);
        if ($trimmed === '') {
            throw new DomainException('AI processing task error cannot be empty.');
        }

        return mb_substr($trimmed, 0, 2000);
    }
}
