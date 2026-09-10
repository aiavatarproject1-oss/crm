<?php

namespace App\Repositories\MongoDB;

use App\Models\Rule;
use App\Repositories\Contracts\RuleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class MongoRuleRepository implements RuleRepositoryInterface
{
    public function find(string $id): ?Rule
    {
        return Rule::query()->find($id);
    }

    public function enabledByPriority(): Collection
    {
        return Rule::query()
            ->where('enabled', true)
            ->orderByDesc('priority')
            ->get();
    }

    public function create(array $attributes): Rule
    {
        return Rule::query()->create($attributes);
    }
}
