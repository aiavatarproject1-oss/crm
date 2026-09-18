<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;


final class RuleDocument extends BaseDocument
{
    /**
     * Eloquent uses $table as the MongoDB collection name.
     */
    protected $table = 'rules';

    protected function casts(): array
    {
        return ['priority' => 'integer', 'enabled' => 'boolean', 'version' => 'integer'];
    }
}
