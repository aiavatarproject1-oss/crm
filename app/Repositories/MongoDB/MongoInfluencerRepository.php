<?php

namespace App\Repositories\MongoDB;

use App\Models\Influencer;
use App\Repositories\Contracts\InfluencerRepositoryInterface;

class MongoInfluencerRepository implements InfluencerRepositoryInterface
{
    public function find(string $id): ?Influencer
    {
        return Influencer::query()->find($id);
    }

    public function findBySlug(string $slug): ?Influencer
    {
        return Influencer::query()->where('slug', $slug)->first();
    }

    public function create(array $attributes): Influencer
    {
        return Influencer::query()->create($attributes);
    }
}
