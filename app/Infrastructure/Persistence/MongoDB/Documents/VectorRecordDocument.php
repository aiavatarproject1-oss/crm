<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;


final class VectorRecordDocument extends BaseDocument
{
    /**
     * Eloquent uses $table as the MongoDB collection name.
     */
    protected $table = 'knowledge_vectors';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['dimensions' => 'integer'];
    }
}
