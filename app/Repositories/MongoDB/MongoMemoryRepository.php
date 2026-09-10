<?php

namespace App\Repositories\MongoDB;

use App\Models\Memory;
use App\Repositories\Contracts\MemoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class MongoMemoryRepository implements MemoryRepositoryInterface
{
    public function find(string $id): ?Memory
    {
        return Memory::query()->find($id);
    }

    public function forUser(string $userId): Collection
    {
        return Memory::query()
            ->where('user_id', $userId)
            ->orderByDesc('importance_score')
            ->get();
    }

    public function create(array $attributes): Memory
    {
        return Memory::query()->create($attributes);
    }
}
