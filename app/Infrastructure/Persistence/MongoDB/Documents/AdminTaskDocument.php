<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;


final class AdminTaskDocument extends BaseDocument
{
    /**
     * Eloquent uses $table as the MongoDB collection name.
     */
    protected $table = 'admin_tasks';

}
