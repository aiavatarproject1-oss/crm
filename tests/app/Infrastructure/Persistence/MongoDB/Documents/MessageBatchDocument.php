<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

use MongoDB\Laravel\Eloquent\Model;

final class MessageBatchDocument extends Model
{
    protected $connection = 'mongodb';

    /**
     * Eloquent uses $table as the MongoDB collection name.
     * $collection is ignored by mongodb/laravel-mongodb and must not be relied on.
     */
    protected $table = 'message_batches';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
