<?php

namespace App\Repositories\Contracts;

use App\Models\Memory;
use Illuminate\Database\Eloquent\Collection;

interface MemoryRepositoryInterface
{
    public function find(string $id): ?Memory;

    public function forUser(string $userId): Collection;

    public function create(array $attributes): Memory;
}
