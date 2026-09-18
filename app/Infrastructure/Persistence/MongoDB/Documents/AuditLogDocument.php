<?php

namespace App\Infrastructure\Persistence\MongoDB\Documents;

final class AuditLogDocument extends BaseDocument
{
    protected $table = 'audit_logs';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }
}
