<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;


final class KnowledgeDocumentDocument extends BaseDocument
{
    /**
     * Eloquent uses $table as the MongoDB collection name.
     */
    protected $table = 'knowledge_documents';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }
}
