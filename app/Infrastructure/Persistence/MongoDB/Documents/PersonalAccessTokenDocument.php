<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\PersonalAccessToken;
use MongoDB\Laravel\Eloquent\DocumentModel;

/**
 * Sanctum token stored in MongoDB (`personal_access_tokens`).
 */
final class PersonalAccessTokenDocument extends PersonalAccessToken
{
    use DocumentModel;
    use SoftDeletes;

    protected $connection = 'mongodb';

    protected $table = 'personal_access_tokens';

    protected $primaryKey = '_id';

    protected $keyType = 'string';
}
