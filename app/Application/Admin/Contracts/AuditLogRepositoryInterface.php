<?php

namespace App\Application\Admin\Contracts;

use App\Domain\Admin\Entities\AuditLog;
use App\Domain\Shared\Contracts\RepositoryInterface;

interface AuditLogRepositoryInterface extends RepositoryInterface
{
    public function append(AuditLog $log): void;

    /**
     * @param  array{actor_id?: string|null, action?: string|null, target_type?: string|null, target_id?: string|null, from?: string|null, to?: string|null}  $filters
     * @return array{items: list<AuditLog>, total: int}
     */
    public function paginate(int $page, int $perPage, array $filters = []): array;
}
