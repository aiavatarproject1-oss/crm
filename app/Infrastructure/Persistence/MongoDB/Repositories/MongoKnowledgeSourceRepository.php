<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\KnowledgeSourceRepositoryInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Knowledge\Entities\KnowledgeSource;
use App\Domain\Knowledge\ValueObjects\KnowledgeSourceId;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\KnowledgeSourceDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\KnowledgeSourceMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;
use MongoDB\Collection;

final class MongoKnowledgeSourceRepository implements KnowledgeSourceRepositoryInterface
{
    private bool $indexesEnsured = false;

    public function __construct(private readonly KnowledgeSourceMapper $mapper) {}

    public function save(KnowledgeSource $source): void
    {
        $this->ensureIndexes();
        ExplicitIdPersister::save(
            $this->mapper->toDocument($source),
            (string) $source->id(),
        );
    }

    public function findById(TenantId $tenantId, InfluencerId $influencerId, KnowledgeSourceId $sourceId): ?KnowledgeSource
    {
        $document = KnowledgeSourceDocument::query()
            ->where('_id', (string) $sourceId)
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findByChecksum(TenantId $tenantId, InfluencerId $influencerId, string $checksum): ?KnowledgeSource
    {
        $document = KnowledgeSourceDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('checksum', $checksum)
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    private function ensureIndexes(): void
    {
        if ($this->indexesEnsured) {
            return;
        }

        KnowledgeSourceDocument::raw(static function (Collection $collection): void {
            $collection->createIndexes([
                [
                    'key' => ['tenant_id' => 1, 'influencer_id' => 1, 'checksum' => 1],
                    'name' => 'knowledge_sources_scope_checksum',
                ],
                [
                    'key' => ['tenant_id' => 1, 'influencer_id' => 1, 'status' => 1, 'created_at' => -1],
                    'name' => 'knowledge_sources_scope_status_created',
                ],
            ]);
        });
        $this->indexesEnsured = true;
    }
}
