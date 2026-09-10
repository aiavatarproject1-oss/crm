<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\MemoryRepositoryInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Memory\Entities\Memory;
use App\Domain\Memory\ValueObjects\MemoryId;
use App\Domain\Memory\ValueObjects\MemoryStatus;
use App\Domain\Memory\ValueObjects\MemoryType;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Persistence\MongoDB\Documents\MemoryDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\MemoryMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;
use MongoDB\Collection;

final class MongoMemoryRepository implements MemoryRepositoryInterface
{
    private bool $indexesEnsured = false;

    public function __construct(private readonly MemoryMapper $mapper) {}

    public function save(Memory $memory): void
    {
        $this->ensureIndexes();
        ExplicitIdPersister::save(
            $this->mapper->toDocument($memory),
            (string) $memory->id(),
        );
    }

    public function findById(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, MemoryId $memoryId): ?Memory
    {
        $document = MemoryDocument::query()
            ->where('_id', (string) $memoryId)
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('user_id', (string) $userId)
            ->first();

        return $document === null ? null : $this->mapper->toDomain($document);
    }

    public function findActiveForUser(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, int $limit = 50): array
    {
        return $this->searchByScope($tenantId, $influencerId, $userId, MemoryStatus::active(), null, $limit);
    }

    public function findByType(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, MemoryType $type): array
    {
        return $this->searchByScope($tenantId, $influencerId, $userId, null, $type, 100);
    }

    public function searchByScope(
        TenantId $tenantId,
        InfluencerId $influencerId,
        UserId $userId,
        ?MemoryStatus $status = null,
        ?MemoryType $type = null,
        int $limit = 100,
    ): array {
        $query = MemoryDocument::query()
            ->where('tenant_id', (string) $tenantId)
            ->where('influencer_id', (string) $influencerId)
            ->where('user_id', (string) $userId);

        if ($status !== null) {
            $query->where('status', $status->value);
        }
        if ($type !== null) {
            $query->where('type', $type->value);
        }

        return $query
            ->orderByDesc('importance_score')
            ->limit($limit)
            ->get()
            ->map(fn (MemoryDocument $document) => $this->mapper->toDomain($document))
            ->all();
    }

    public function findImportantUserMemories(TenantId $tenantId, InfluencerId $influencerId, UserId $userId, int $limit): array
    {
        return $this->findActiveForUser($tenantId, $influencerId, $userId, $limit);
    }

    private function ensureIndexes(): void
    {
        if ($this->indexesEnsured) {
            return;
        }

        MemoryDocument::raw(static function (Collection $collection): void {
            $collection->createIndexes([
                [
                    'key' => [
                        'tenant_id' => 1,
                        'influencer_id' => 1,
                        'user_id' => 1,
                        'status' => 1,
                        'importance_score' => -1,
                    ],
                    'name' => 'memories_scope_status_importance',
                ],
                [
                    'key' => [
                        'tenant_id' => 1,
                        'influencer_id' => 1,
                        'user_id' => 1,
                        'type' => 1,
                    ],
                    'name' => 'memories_scope_type',
                ],
            ]);
        });
        $this->indexesEnsured = true;
    }
}
