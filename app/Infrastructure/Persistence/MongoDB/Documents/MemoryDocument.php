<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

use MongoDB\Laravel\Eloquent\Model;

final class MemoryDocument extends Model
{
    protected $connection = 'mongodb';

    /**
     * Eloquent uses $table as the MongoDB collection name.
     */
    protected $table = 'memories';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'float',
            'importance_score' => 'float',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
