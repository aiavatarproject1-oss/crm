<?php

namespace App\Repositories\Contracts;

use App\Models\Influencer;

interface InfluencerRepositoryInterface
{
    public function find(string $id): ?Influencer;

    public function findBySlug(string $slug): ?Influencer;

    public function create(array $attributes): Influencer;
}
