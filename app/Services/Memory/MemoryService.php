<?php

namespace App\Services\Memory;

use App\Repositories\Contracts\MemoryRepositoryInterface;

class MemoryService
{
    public function __construct(
        protected MemoryRepositoryInterface $memories,
    ) {}
}
