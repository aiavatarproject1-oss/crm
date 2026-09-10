<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Message extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'mongodb';

    protected $table = 'messages';

    protected $fillable = [
        'conversation_id', 'user_id', 'role', 'text', 'platform', 'model',
        'latency_ms', 'prompt_tokens', 'completion_tokens', 'metadata', 'tokens',
    ];

    protected function casts(): array
    {
        return [
            'latency_ms' => 'integer', 'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer', 'tokens' => 'integer',
            'metadata' => 'array', 'created_at' => 'datetime',
        ];
    }
}
