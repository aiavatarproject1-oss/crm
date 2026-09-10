<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

use MongoDB\Laravel\Eloquent\Model;

final class ConversationDocument extends Model
{
    protected $connection = 'mongodb';

    /**
     * Eloquent uses $table as the MongoDB collection name.
     * $collection is ignored by mongodb/laravel-mongodb and must not be relied on.
     */
    protected $table = 'conversations';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
        ];
    }
}
