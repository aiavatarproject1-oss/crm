<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

/**
 * High-volume pipeline trace (TTL-expired; still soft-deletable for admin cleanup).
 */
final class PipelineEventDocument extends BaseDocument
{
    protected $table = 'pipeline_events';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
