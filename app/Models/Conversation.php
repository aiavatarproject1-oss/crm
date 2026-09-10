<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Conversation extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'conversations';

    protected $fillable = ['user_id', 'influencer_id', 'platform', 'status', 'last_message_at', 'metadata'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime', 'metadata' => 'array'];
    }
}
