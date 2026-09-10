<?php

namespace App\Repositories\Contracts;

use App\Models\Rule;
use Illuminate\Database\Eloquent\Collection;

interface RuleRepositoryInterface
{
    public function find(string $id): ?Rule;

    public function enabledByPriority(): Collection;

    public function create(array $attributes): Rule;
}
