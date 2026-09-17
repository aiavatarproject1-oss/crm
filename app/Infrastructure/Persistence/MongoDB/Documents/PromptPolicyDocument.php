<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

use MongoDB\Laravel\Eloquent\Model;

final class PromptPolicyDocument extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'prompt_policies';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'style_lines' => 'array',
            'stage_lines' => 'array',
        ];
    }
}
