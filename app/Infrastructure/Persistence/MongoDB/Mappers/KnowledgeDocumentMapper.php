<?php

namespace App\Infrastructure\Persistence\MongoDB\Mappers;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeDocument;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentId;
use App\Domain\Knowledge\ValueObjects\KnowledgeDocumentStatus;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceId;
use App\Domain\Knowledge\ValueObjects\KnowledgeType;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\KnowledgeDocumentDocument;

final class KnowledgeDocumentMapper
{
    public function toDocument(KnowledgeDocument $knowledgeDocument): KnowledgeDocumentDocument
    {
        $document = new KnowledgeDocumentDocument([
            'document_id' => (string) $knowledgeDocument->id(),
            'tenant_id' => (string) $knowledgeDocument->tenantId,
            'influencer_id' => (string) $knowledgeDocument->influencerId,
            'type' => $knowledgeDocument->type->value,
            'title' => $knowledgeDocument->title,
            'content' => $knowledgeDocument->content,
            'version' => $knowledgeDocument->version,
            'status' => $knowledgeDocument->status->value,
            'source' => $knowledgeDocument->source,
            'source_id' => $knowledgeDocument->sourceId === null ? null : (string) $knowledgeDocument->sourceId,
            'checksum' => $knowledgeDocument->checksum,
            'metadata' => $knowledgeDocument->metadata,
        ]);
        $document->setAttribute('_id', $this->storageId($knowledgeDocument));

        return $document;
    }

    public function toDomain(KnowledgeDocumentDocument $document): KnowledgeDocument
    {
        $sourceId = $document->source_id ?? null;

        return new KnowledgeDocument(
            new KnowledgeDocumentId((string) $document->document_id),
            new TenantId((string) $document->tenant_id),
            new InfluencerId((string) $document->influencer_id),
            KnowledgeType::from((string) $document->type),
            (string) $document->title,
            (string) $document->content,
            (int) $document->version,
            new KnowledgeDocumentStatus((string) ($document->status ?? KnowledgeDocumentStatus::ACTIVE)),
            (string) $document->source,
            (array) ($document->metadata ?? []),
            $sourceId === null || $sourceId === '' ? null : new KnowledgeSourceId((string) $sourceId),
            isset($document->checksum) ? (string) $document->checksum : null,
        );
    }

    private function storageId(KnowledgeDocument $document): string
    {
        return implode(':', [(string) $document->tenantId, (string) $document->influencerId, (string) $document->id(), 'v'.$document->version]);
    }
}
