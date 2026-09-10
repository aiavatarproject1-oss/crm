<?php

namespace App\Application\Contracts;

use App\Domain\Influencer\ValueObjects\InfluencerId;
use App\Domain\Quality\Entities\AdminTask;
use App\Domain\Shared\Contracts\RepositoryInterface;
use App\Domain\Tenant\ValueObjects\TenantId;

interface AdminTaskRepositoryInterface extends RepositoryInterface
{
    public function save(AdminTask $task): void;

    /**
     * @return list<AdminTask>
     */
    public function findPending(
        ?TenantId $tenantId = null,
        ?InfluencerId $influencerId = null,
        int $limit = 50,
    ): array;
}
