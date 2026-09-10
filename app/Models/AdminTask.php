<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class AdminTask extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'admin_tasks';

    protected $fillable = ['conversation_id', 'user_id', 'reason', 'status', 'priority', 'metadata'];

    protected function casts(): array
    {
        return ['priority' => 'integer', 'metadata' => 'array'];
    }
}
