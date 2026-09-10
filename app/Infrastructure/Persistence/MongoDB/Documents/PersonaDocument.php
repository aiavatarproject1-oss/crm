<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

use MongoDB\Laravel\Eloquent\Model;

final class PersonaDocument extends Model
{
    protected $connection = 'mongodb';

    /**
     * Eloquent uses $table as the MongoDB collection name.
     */
    protected $table = 'personas';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['system_rules' => 'array', 'metadata' => 'array'];
    }
}
