<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    public function find(string $id): ?User;

    public function findByPlatformIdentity(string $platform, string $platformUserId, string $influencerId): ?User;

    public function create(array $attributes): User;
}
