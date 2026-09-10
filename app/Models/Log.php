<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Log extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'logs';

    protected $fillable = ['level', 'service', 'event', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
