<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $table = 'users';

    protected $fillable = ['platform', 'platform_user_id', 'username', 'language', 'influencer_id', 'metadata', 'last_seen_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'last_seen_at' => 'datetime'];
    }
}
