<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

use MongoDB\Laravel\Eloquent\Model;

final class VectorRecordDocument extends Model
{
    protected $connection = 'mongodb';

    /**
     * Eloquent uses $table as the MongoDB collection name.
     */
    protected $table = 'knowledge_vectors';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['dimensions' => 'integer'];
    }
}
