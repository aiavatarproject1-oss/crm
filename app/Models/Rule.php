<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Rule extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'rules';

    protected $fillable = ['name', 'type', 'patterns', 'priority', 'action', 'enabled', 'metadata'];

    protected function casts(): array
    {
        return ['patterns' => 'array', 'priority' => 'integer', 'enabled' => 'boolean', 'metadata' => 'array'];
    }
}
