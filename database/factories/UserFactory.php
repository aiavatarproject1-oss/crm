<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'platform' => 'test',
            'platform_user_id' => fake()->unique()->uuid(),
            'username' => fake()->userName(),
            'language' => 'en',
            'influencer_id' => fake()->uuid(),
            'metadata' => [],
            'last_seen_at' => now(),
        ];
    }
}
