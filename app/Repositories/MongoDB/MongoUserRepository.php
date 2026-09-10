<?php

namespace App\Repositories\MongoDB;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class MongoUserRepository implements UserRepositoryInterface
{
    public function find(string $id): ?User
    {
        return User::query()->find($id);
    }

    public function findByPlatformIdentity(string $platform, string $platformUserId, string $influencerId): ?User
    {
        return User::query()
            ->where('platform', $platform)
            ->where('platform_user_id', $platformUserId)
            ->where('influencer_id', $influencerId)
            ->first();
    }

    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }
}
