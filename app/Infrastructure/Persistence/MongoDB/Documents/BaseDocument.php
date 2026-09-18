<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

use Illuminate\Database\Eloquent\SoftDeletes;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Base for every application collection: MongoDB connection, soft deletes, string _id.
 *
 * Soft-deleted documents are excluded from all repository queries automatically;
 * use `withTrashed()` / `onlyTrashed()` explicitly when an admin needs to see them.
 */
abstract class BaseDocument extends Model
{
    use SoftDeletes;

    protected $connection = 'mongodb';

    protected $keyType = 'string';

    /** Mappers hydrate documents via constructor / forceFill. */
    protected $guarded = [];
}
