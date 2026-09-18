<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;


final class UserDocument extends BaseDocument
{
    /**
     * Eloquent uses $table as the MongoDB collection name.
     * $collection is ignored by mongodb/laravel-mongodb and must not be relied on.
     */
    protected $table = 'users';

    public $timestamps = false;

}
