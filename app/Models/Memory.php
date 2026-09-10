<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Memory extends Model
{
    public const TYPES = ['fact', 'preference', 'emotion', 'behavior'];

    protected $connection = 'mongodb';

    protected $table = 'memories';

    protected $fillable = ['user_id', 'influencer_id', 'type', 'content', 'importance_score', 'metadata'];

    protected function casts(): array
    {
        return ['importance_score' => 'float', 'metadata' => 'array'];
    }
}
