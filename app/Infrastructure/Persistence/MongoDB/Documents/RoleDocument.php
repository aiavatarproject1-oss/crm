<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

final class RoleDocument extends BaseDocument
{
    protected $table = 'roles';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
