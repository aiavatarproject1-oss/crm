<?php

namespace App\Infrastructure\Persistence\MongoDB\Repositories;

use App\Application\Contracts\AdminTaskRepositoryInterface;
use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Quality\Entities\AdminTask;
use App\Domain\Tenant\ValueObjects\TenantId;
use App\Infrastructure\Persistence\MongoDB\Documents\AdminTaskDocument;
use App\Infrastructure\Persistence\MongoDB\Mappers\AdminTaskMapper;
use App\Infrastructure\Persistence\MongoDB\Support\ExplicitIdPersister;

final class MongoAdminTaskRepository implements AdminTaskRepositoryInterface
{
    public function __construct(private readonly AdminTaskMapper $mapper) {}

    public function save(AdminTask $task): void
    {
        ExplicitIdPersister::save(
            $this->mapper->toDocument($task),
            (string) $task->id(),
            static fn () => AdminTaskDocument::query()
                ->where('tenant_id', (string) $task->tenantId)
                ->where('influencer_id', (string) $task->influencerId)
                ->where('message_id', (string) $task->messageId)
                ->first(),
        );
    }

    public function findPending(
        ?TenantId $tenantId = null,
        ?InfluencerId $influencerId = null,
        int $limit = 50,
    ): array {
        $query = AdminTaskDocument::query()->where('status', 'pending');
        if ($tenantId !== null) {
            $query->where('tenant_id', (string) $tenantId);
        }
        if ($influencerId !== null) {
            $query->where('influencer_id', (string) $influencerId);
        }

        return $query
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (AdminTaskDocument $document) => $this->mapper->toDomain($document))
            ->all();
    }
}
