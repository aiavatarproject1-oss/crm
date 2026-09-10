<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeChunk;
use App\Domain\Knowledge\ValueObjects\KnowledgeChunkId;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\KnowledgeChunkDocument;

final class KnowledgeChunkMapper
{
    public function toDocument(KnowledgeChunk $chunk): KnowledgeChunkDocument
    {
        $document = new KnowledgeChunkDocument([
            'tenant_id' => (string) $chunk->tenantId,
            'influencer_id' => (string) $chunk->influencerId,
            'document_id' => (string) $chunk->documentId,
            'content' => $chunk->content,
            'position' => $chunk->position,
            'token_count' => $chunk->tokenCount,
            'document_version' => $chunk->documentVersion,
            'metadata' => $chunk->metadata,
        ]);
        $document->setAttribute('_id', (string) $chunk->id());

        return $document;
    }

    public function toDomain(KnowledgeChunkDocument $document): KnowledgeChunk
    {
        return new KnowledgeChunk(
            new KnowledgeChunkId((string) $document->getAttribute('_id')),
            new TenantId((string) $document->tenant_id),
            new InfluencerId((string) $document->influencer_id),
            new KnowledgeDocumentId((string) $document->document_id),
            (string) $document->content,
            (int) $document->position,
            (int) $document->token_count,
            (array) ($document->metadata ?? []),
            (int) ($document->document_version ?? 1),
        );
    }
}
