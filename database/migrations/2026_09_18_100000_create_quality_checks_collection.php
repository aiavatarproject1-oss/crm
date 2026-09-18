<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('mongodb');
        $indexes = function (Blueprint $blueprint): void {
            $blueprint->dropIndexIfExists('quality_checks_deleted_at_index');
            $blueprint->index(['deleted_at' => 1], 'quality_checks_deleted_at_index');
            $blueprint->dropIndexIfExists('quality_checks_created_index');
            $blueprint->index(['created_at' => -1], 'quality_checks_created_index');
            $blueprint->dropIndexIfExists('quality_checks_character_created_index');
            $blueprint->index(['character_id' => 1, 'created_at' => -1], 'quality_checks_character_created_index');
            $blueprint->dropIndexIfExists('quality_checks_approved_created_index');
            $blueprint->index(['approved' => 1, 'created_at' => -1], 'quality_checks_approved_created_index');
            $blueprint->dropIndexIfExists('quality_checks_conversation_created_index');
            $blueprint->index(['conversation_id' => 1, 'created_at' => -1], 'quality_checks_conversation_created_index');
        };

        if ($schema->hasTable('quality_checks')) {
            $schema->table('quality_checks', $indexes);
        } else {
            $schema->create('quality_checks', $indexes);
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('mongodb');
        if ($schema->hasTable('quality_checks')) {
            $schema->drop('quality_checks');
        }
    }
};
