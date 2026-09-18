<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;


final class PersonaDocument extends BaseDocument
{
    /**
     * Eloquent uses $table as the MongoDB collection name.
     */
    protected $table = 'personas';
}
