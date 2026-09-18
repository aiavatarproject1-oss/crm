<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;


final class KnowledgeChunkDocument extends BaseDocument
{
    /**
     * Eloquent uses $table as the MongoDB collection name.
     */
    protected $table = 'knowledge_chunks';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['position' => 'integer', 'token_count' => 'integer'];
    }
}
