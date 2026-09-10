<?php

namespace Database\Seeders;

use App\Models\Influencer;
use Illuminate\Database\Seeder;

class InfluencerSeeder extends Seeder
{
    public function run(): void
    {
        Influencer::query()->updateOrCreate(
            ['slug' => 'sofia'],
            ['name' => 'Sofia', 'persona' => null, 'language' => 'en', 'active' => true, 'settings' => []],
        );
    }
}
