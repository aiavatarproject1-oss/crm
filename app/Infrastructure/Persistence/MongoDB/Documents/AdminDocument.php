<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Sanctum-authenticatable admin record. Domain logic lives in App\Domain\Admin\Entities\Admin;
 * this class only exists so Laravel's auth guard + Sanctum can resolve the current admin.
 */
final class AdminDocument extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use HasApiTokens;
    use SoftDeletes;

    protected $connection = 'mongodb';

    protected $table = 'admins';

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    /** @var list<string> */
    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_super_admin' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return (string) $this->getAttribute('password_hash');
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }
}
