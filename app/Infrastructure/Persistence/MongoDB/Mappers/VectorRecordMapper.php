<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\VectorRecord;
use App\Domain\Knowledge\ValueObjects\KnowledgeChunkId;
use App\Domain\Knowledge\ValueObjects\VectorRecordId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\VectorRecordDocument;

final class VectorRecordMapper
{
    public function toDocument(VectorRecord $record): VectorRecordDocument
    {
        $document = new VectorRecordDocument([
            'tenant_id' => (string) $record->tenantId,
            'influencer_id' => (string) $record->influencerId,
            'chunk_id' => (string) $record->chunkId,
            'embedding_model' => $record->embeddingModel,
            'vector' => $record->vector,
            'embedding' => $record->vector, // legacy field for older readers
            'dimensions' => $record->dimensions,
            'content_hash' => $record->contentHash,
            'metadata' => $record->metadata,
        ]);
        $document->setAttribute('_id', (string) $record->id());

        return $document;
    }

    public function toDomain(VectorRecordDocument $document): VectorRecord
    {
        $vector = $document->vector ?? $document->embedding ?? [];

        return new VectorRecord(
            new VectorRecordId((string) $document->getAttribute('_id')),
            new TenantId((string) $document->tenant_id),
            new InfluencerId((string) $document->influencer_id),
            new KnowledgeChunkId((string) $document->chunk_id),
            array_map('floatval', (array) $vector),
            (int) $document->dimensions,
            (string) ($document->embedding_model ?? data_get($document->metadata, 'model', 'unknown')),
            (string) ($document->content_hash ?: ('legacy-'.(string) $document->getAttribute('_id'))),
            (array) ($document->metadata ?? []),
        );
    }
}
