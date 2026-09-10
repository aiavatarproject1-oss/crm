<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class KnowledgeDocument extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'knowledge_documents';

    protected $fillable = [
        'influencer_id', 'type', 'title', 'content', 'embedding_reference',
        'vector_id', 'embedding_status', 'chunk_index', 'metadata',
    ];

    protected function casts(): array
    {
        return ['chunk_index' => 'integer', 'metadata' => 'array'];
    }
}
