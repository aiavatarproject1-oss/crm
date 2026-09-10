<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Phase 13.2 — indexes on actual runtime collections ($table names).
 * Idempotent via dropIndexIfExists before create.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('rules', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('rules_enabled_priority_index');
            $collection->dropIndexIfExists('rules_tenant_influencer_enabled_priority_index');
            $collection->index(
                ['tenant_id' => 1, 'influencer_id' => 1, 'enabled' => 1, 'priority' => -1],
                'rules_tenant_influencer_enabled_priority_index',
            );
        });

        Schema::connection('mongodb')->table('personas', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('personas_tenant_influencer_unique');
            $collection->unique(
                ['tenant_id' => 1, 'influencer_id' => 1],
                'personas_tenant_influencer_unique',
            );
        });

        Schema::connection('mongodb')->table('memories', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('memories_user_importance_index');
            $collection->dropIndexIfExists('memories_tenant_influencer_user_importance_index');
            $collection->index(
                ['tenant_id' => 1, 'influencer_id' => 1, 'user_id' => 1, 'importance_score' => -1],
                'memories_tenant_influencer_user_importance_index',
            );
        });

        Schema::connection('mongodb')->table('knowledge_documents', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('knowledge_scope_type_version');
            $collection->dropIndexIfExists('knowledge_document_version_unique');
            $collection->dropIndexIfExists('knowledge_documents_influencer_id_index');
            $collection->index(
                ['tenant_id' => 1, 'influencer_id' => 1, 'type' => 1, 'version' => -1],
                'knowledge_scope_type_version',
            );
            $collection->unique(
                ['tenant_id' => 1, 'influencer_id' => 1, 'document_id' => 1, 'version' => 1],
                'knowledge_document_version_unique',
            );
        });

        Schema::connection('mongodb')->table('knowledge_chunks', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('knowledge_chunk_scope_position');
            $collection->index(
                ['tenant_id' => 1, 'influencer_id' => 1, 'document_id' => 1, 'position' => 1],
                'knowledge_chunk_scope_position',
            );
        });

        Schema::connection('mongodb')->table('knowledge_vectors', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('knowledge_vector_scope_chunk');
            $collection->index(
                ['tenant_id' => 1, 'influencer_id' => 1, 'chunk_id' => 1],
                'knowledge_vector_scope_chunk',
            );
        });

        Schema::connection('mongodb')->table('admin_tasks', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('admin_task_scope_status');
            $collection->dropIndexIfExists('admin_task_message_unique');
            $collection->dropIndexIfExists('admin_tasks_conversation_id_index');
            $collection->dropIndexIfExists('admin_tasks_user_id_index');
            $collection->index(
                ['tenant_id' => 1, 'influencer_id' => 1, 'status' => 1],
                'admin_task_scope_status',
            );
            $collection->unique(
                ['tenant_id' => 1, 'influencer_id' => 1, 'message_id' => 1],
                'admin_task_message_unique',
            );
        });

        Schema::connection('mongodb')->table('messages', function (Blueprint $collection): void {
            $collection->dropIndexIfExists('messages_conversation_created_at_index');
            $collection->index(
                ['conversation_id' => 1, 'created_at' => 1],
                'messages_conversation_created_at_index',
            );
        });
    }

    public function down(): void
    {
        $indexes = [
            'rules' => ['rules_tenant_influencer_enabled_priority_index'],
            'personas' => ['personas_tenant_influencer_unique'],
            'memories' => ['memories_tenant_influencer_user_importance_index'],
            'knowledge_documents' => ['knowledge_scope_type_version', 'knowledge_document_version_unique'],
            'knowledge_chunks' => ['knowledge_chunk_scope_position'],
            'knowledge_vectors' => ['knowledge_vector_scope_chunk'],
            'admin_tasks' => ['admin_task_scope_status', 'admin_task_message_unique'],
            'messages' => ['messages_conversation_created_at_index'],
        ];

        foreach ($indexes as $collection => $names) {
            Schema::connection('mongodb')->table($collection, function (Blueprint $blueprint) use ($names): void {
                foreach ($names as $name) {
                    $blueprint->dropIndexIfExists($name);
                }
            });
        }
    }
};
