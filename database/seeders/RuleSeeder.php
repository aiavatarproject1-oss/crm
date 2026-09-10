<?php

namespace Database\Seeders;

use App\Models\Rule;
use Illuminate\Database\Seeder;

class RuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['video_call_request', 'voice_request', 'spam', 'advertisement', 'human_request'] as $name) {
            Rule::query()->updateOrCreate(
                ['name' => $name],
                [
                    'type' => 'placeholder',
                    'patterns' => [],
                    'priority' => 0,
                    'action' => 'review',
                    'enabled' => true,
                    'metadata' => [],
                ],
            );
        }
    }
}
