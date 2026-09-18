<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;


final class KnowledgeSourceDocument extends BaseDocument
{
    protected $table = 'knowledge_sources';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
