<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Memory\ValueObjects\MemoryId;
use App\Domain\Memory\ValueObjects\MemoryStatus;
use App\Domain\Memory\ValueObjects\MemoryType;
use App\Domain\Message\ValueObjects\MessageBatchId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Persistence\MongoDB\Documents\MemoryDocument;
use DateTimeImmutable;

final class MemoryMapper
{
    public function toDocument(Memory $memory): MemoryDocument
    {
        $document = new MemoryDocument([
            'tenant_id' => (string) $memory->tenantId,
            'influencer_id' => (string) $memory->influencerId,
            'user_id' => (string) $memory->userId,
            'type' => $memory->type->value,
            'content' => $memory->content,
            'confidence_score' => $memory->confidenceScore,
            'importance_score' => $memory->importanceScore,
            'status' => $memory->status()->value,
            'source_message_batch_id' => $memory->sourceMessageBatchId === null ? null : (string) $memory->sourceMessageBatchId,
            'superseded_by' => $memory->supersededById() === null ? null : (string) $memory->supersededById(),
            'metadata' => $memory->metadata,
            'created_at' => $memory->createdAt,
            'updated_at' => $memory->updatedAt(),
        ]);
        $document->setAttribute('_id', (string) $memory->id());

        return $document;
    }

    public function toDomain(MemoryDocument $document): Memory
    {
        $batchId = $document->source_message_batch_id ?? null;
        $supersededBy = $document->superseded_by ?? null;
        $createdAt = $document->created_at ?? null;
        $updatedAt = $document->updated_at ?? $createdAt;
        $createdAt ??= new DateTimeImmutable;
        $updatedAt ??= $createdAt;

        return Memory::reconstitute(
            new MemoryId((string) $document->getAttribute('_id')),
            new TenantId((string) $document->tenant_id),
            new InfluencerId((string) $document->influencer_id),
            new UserId((string) $document->user_id),
            new MemoryType((string) $document->type),
            (string) $document->content,
            (float) ($document->confidence_score ?? 1.0),
            (float) $document->importance_score,
            new MemoryStatus((string) ($document->status ?? MemoryStatus::ACTIVE)),
            $batchId === null || $batchId === '' ? null : new MessageBatchId((string) $batchId),
            (array) ($document->metadata ?? []),
            $createdAt instanceof DateTimeImmutable ? $createdAt : new DateTimeImmutable((string) $createdAt),
            $updatedAt instanceof DateTimeImmutable ? $updatedAt : new DateTimeImmutable((string) $updatedAt),
            $supersededBy === null || $supersededBy === '' ? null : new MemoryId((string) $supersededBy),
        );
    }
}
