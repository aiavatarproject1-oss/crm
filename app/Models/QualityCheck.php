<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class QualityCheck extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'quality_checks';

    protected $fillable = ['message_id', 'score', 'approved', 'issues', 'suggestions', 'metadata'];

    protected function casts(): array
    {
        return ['score' => 'float', 'approved' => 'boolean', 'issues' => 'array', 'suggestions' => 'array', 'metadata' => 'array'];
    }
}
