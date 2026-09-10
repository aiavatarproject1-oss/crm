<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

use MongoDB\Laravel\Eloquent\Model;

final class RuleDocument extends Model
{
    protected $connection = 'mongodb';

    /**
     * Eloquent uses $table as the MongoDB collection name.
     */
    protected $table = 'rules';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['patterns' => 'array', 'priority' => 'integer', 'enabled' => 'boolean', 'version' => 'integer', 'metadata' => 'array'];
    }
}
