<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;


final class AiProcessingTaskDocument extends BaseDocument
{
    /**
     * Eloquent uses $table as the MongoDB collection name.
     * $collection is ignored by mongodb/laravel-mongodb and must not be relied on.
     */
    protected $table = 'ai_processing_tasks';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'attempts' => 'integer',
        ];
    }
}
