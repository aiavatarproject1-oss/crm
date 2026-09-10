<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

use MongoDB\Laravel\Eloquent\Model;

final class KnowledgeDocumentDocument extends Model
{
    protected $connection = 'mongodb';

    /**
     * Eloquent uses $table as the MongoDB collection name.
     */
    protected $table = 'knowledge_documents';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
