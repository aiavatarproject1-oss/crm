<?php

namespace App\Domain\Tenant\Entities;

use App\Domain\Shared\Contracts\Entity;
use App\Domain\Tenant\ValueObjects\TenantId;

final readonly class Tenant implements Entity
{
    public function __construct(private TenantId $tenantId, public string $name) {}

    public function id(): TenantId
    {
        return $this->tenantId;
    }
}
