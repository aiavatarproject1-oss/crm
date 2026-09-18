<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;


final class MemoryDocument extends BaseDocument
{
    /**
     * Eloquent uses $table as the MongoDB collection name.
     */
    protected $table = 'memories';

    public $timestamps = false;

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
