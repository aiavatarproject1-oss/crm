<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Influencer extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'influencers';

    protected $fillable = ['name', 'slug', 'persona', 'language', 'active', 'settings'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'settings' => 'array'];
    }
}
