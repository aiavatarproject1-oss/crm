<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

final class CharacterSettingsDocument extends BaseDocument
{
    protected $table = 'characters';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
